import assert from "node:assert/strict";
import { readFile } from "node:fs/promises";

const source = await readFile(
  new URL("../assets/src/embed-parent.js", import.meta.url),
  "utf8",
);
const moduleUrl = `data:text/javascript;base64,${Buffer.from(source).toString("base64")}`;
const protocol = await import(moduleUrl);

class FakeTarget {
  constructor() {
    this.listeners = new Map();
  }

  addEventListener(type, listener) {
    const listeners = this.listeners.get(type) ?? new Set();
    listeners.add(listener);
    this.listeners.set(type, listeners);
  }

  removeEventListener(type, listener) {
    this.listeners.get(type)?.delete(listener);
  }

  dispatch(type, event = {}) {
    for (const listener of this.listeners.get(type) ?? []) {
      listener(event);
    }
  }

  listenerCount(type) {
    return this.listeners.get(type)?.size ?? 0;
  }
}

class FakeMutationObserver {
  static instances = [];

  constructor(callback) {
    this.callback = callback;
    this.disconnected = false;
    FakeMutationObserver.instances.push(this);
  }

  observe() {}

  disconnect() {
    this.disconnected = true;
  }

  trigger() {
    this.callback();
  }
}

class FakeWindow extends FakeTarget {
  constructor() {
    super();
    this.MutationObserver = FakeMutationObserver;
    this.timers = new Map();
    this.nextTimer = 1;
  }

  setTimeout(callback) {
    const id = this.nextTimer++;
    this.timers.set(id, callback);
    return id;
  }

  clearTimeout(id) {
    this.timers.delete(id);
  }

  flushTimers() {
    const callbacks = [...this.timers.values()];
    this.timers.clear();

    for (const callback of callbacks) {
      callback();
    }
  }
}

class FakeIframe extends FakeTarget {
  constructor(source) {
    super();
    this.contentWindow = source;
    this.style = {};
    this.isConnected = true;
    this.ownerDocument = {
      documentElement: {},
    };
  }
}

const instanceOne = "550e8400-e29b-41d4-a716-446655440000";
const instanceTwo = "550e8400-e29b-41d4-a716-446655440001";
const sourceOne = {};
const sourceTwo = {};
const hostWindow = new FakeWindow();
const iframeOne = new FakeIframe(sourceOne);
const iframeTwo = new FakeIframe(sourceTwo);
const readyPayloads = [];
const statusPayloads = [];
let timeoutCount = 0;
const controllerOne = protocol.createTurnierplanEmbedController({
  iframe: iframeOne,
  instance: instanceOne,
  hostWindow,
  minHeight: 240,
  maxHeight: 1200,
  onReady: (payload) => readyPayloads.push(payload),
  onStatus: (payload) => statusPayloads.push(payload),
  onReadyTimeout: () => {
    timeoutCount += 1;
  },
});
const controllerTwo = protocol.createTurnierplanEmbedController({
  iframe: iframeTwo,
  instance: instanceTwo,
  hostWindow,
});

const readyMessage = {
  type: "turnierplan.eu/embed",
  version: 1,
  instance: instanceOne,
  event: "ready",
  payload: {
    view: "matches",
    resolved_language: "de",
    height: 684,
    warnings: [],
  },
};

assert.equal(
  protocol.isTurnierplanEmbedMessage(readyMessage),
  true,
  "A valid ready message must match the V1 contract.",
);

for (const invalidMessage of [
  { ...readyMessage, type: "other-service/embed" },
  { ...readyMessage, version: 2 },
  { ...readyMessage, instance: instanceOne.toUpperCase() },
  { ...readyMessage, extra: true },
  {
    ...readyMessage,
    payload: { ...readyMessage.payload, height: Number.NaN },
  },
  {
    ...readyMessage,
    payload: { ...readyMessage.payload, height: 8001 },
  },
]) {
  assert.equal(
    protocol.isTurnierplanEmbedMessage(invalidMessage),
    false,
    "An invalid message must be rejected.",
  );
}

hostWindow.dispatch("message", {
  origin: "https://attacker.example",
  source: sourceOne,
  data: readyMessage,
});
hostWindow.dispatch("message", {
  origin: "https://www.turnierplan.eu",
  source: sourceTwo,
  data: readyMessage,
});
hostWindow.dispatch("message", {
  origin: "https://www.turnierplan.eu",
  source: sourceOne,
  data: { ...readyMessage, instance: instanceTwo },
});

assert.equal(
  readyPayloads.length,
  0,
  "Origin, source and instance must all match.",
);

hostWindow.dispatch("message", {
  origin: "https://www.turnierplan.eu",
  source: sourceOne,
  data: readyMessage,
});

assert.equal(
  readyPayloads.length,
  1,
  "The matching frame must receive ready once.",
);
assert.equal(
  iframeOne.style.height,
  "684px",
  "Ready must set the bounded height.",
);
assert.equal(
  iframeTwo.style.height,
  undefined,
  "A second frame must remain unchanged.",
);

hostWindow.dispatch("message", {
  origin: "https://www.turnierplan.eu",
  source: sourceOne,
  data: {
    type: "turnierplan.eu/embed",
    version: 1,
    instance: instanceOne,
    event: "resize",
    payload: { height: 8000 },
  },
});

assert.equal(
  iframeOne.style.height,
  "1200px",
  "Configured maximum height must win.",
);

hostWindow.dispatch("message", {
  origin: "https://www.turnierplan.eu",
  source: sourceOne,
  data: {
    type: "turnierplan.eu/embed",
    version: 1,
    instance: instanceOne,
    event: "status",
    payload: {
      state: "stale",
      code: "refresh_failed",
    },
  },
});

assert.deepEqual(
  statusPayloads,
  [{ state: "stale", code: "refresh_failed" }],
  "A valid status must be delivered without remote display text.",
);

const timeoutIframe = new FakeIframe({});
const timeoutController = protocol.createTurnierplanEmbedController({
  iframe: timeoutIframe,
  instance: "550e8400-e29b-41d4-a716-446655440002",
  hostWindow,
  readyTimeoutMilliseconds: 1000,
  onReadyTimeout: () => {
    timeoutCount += 1;
  },
});

assert.equal(
  hostWindow.timers.size,
  0,
  "Lazy frames must not start a timer early.",
);
timeoutController.markRequested();
assert.equal(
  hostWindow.timers.size,
  1,
  "Requesting the lazy frame must start the ready timer.",
);
hostWindow.flushTimers();
assert.equal(
  timeoutCount,
  1,
  "A missing ready event must trigger the cautious timeout.",
);

const listenersBeforeRemoval = hostWindow.listenerCount("message");
iframeTwo.isConnected = false;
FakeMutationObserver.instances[1].trigger();
assert.equal(
  hostWindow.listenerCount("message"),
  listenersBeforeRemoval - 1,
  "Removing an iframe must remove its message listener.",
);

controllerOne.destroy();
controllerTwo.destroy();
timeoutController.destroy();
assert.equal(
  hostWindow.listenerCount("message"),
  0,
  "Destroy must clean every listener.",
);

console.log("embed-parent.test: OK");
