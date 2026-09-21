import assert from "node:assert/strict";
import { readFile } from "node:fs/promises";

const source = (
  await readFile(
    new URL("../assets/src/embed-frontend.js", import.meta.url),
    "utf8",
  )
).replace(
  "import { createTurnierplanEmbedController } from './embed-parent';",
  "const createTurnierplanEmbedController = globalThis.__tpeuController;",
);

globalThis.__tpeuController = () => {
  throw new Error("The injected controller should be used by this test.");
};

const moduleUrl = `data:text/javascript;base64,${Buffer.from(source).toString("base64")}`;
const { createTurnierplanFrontend } = await import(moduleUrl);
delete globalThis.__tpeuController;

class FakeIntersectionObserver {
  static instances = [];

  constructor(callback, options) {
    this.callback = callback;
    this.options = options;
    this.observed = [];
    this.disconnected = false;
    FakeIntersectionObserver.instances.push(this);
  }

  observe(element) {
    this.observed.push(element);
  }

  disconnect() {
    this.disconnected = true;
  }

  intersect() {
    this.callback([{ isIntersecting: true }]);
  }
}

class FakeMutationObserver {
  static instance = null;

  constructor(callback) {
    this.callback = callback;
    this.disconnected = false;
    FakeMutationObserver.instance = this;
  }

  observe() {}

  disconnect() {
    this.disconnected = true;
  }

  trigger(mutation) {
    this.callback([mutation]);
  }
}

const createWrapper = (instance) => {
  const iframe = { instance };
  const states = ["loading", "ready", "empty", "stale", "error", "timeout"].map(
    (state) => ({ dataset: { tpeuState: state }, hidden: state !== "loading" }),
  );

  return {
    dataset: {
      tpeuInstance: instance,
      tpeuOrigin: "https://www.turnierplan.eu",
      tpeuMinHeight: "240",
      tpeuMaxHeight: "4000",
    },
    iframe,
    matches(selector) {
      return selector === "[data-tpeu-embed]";
    },
    querySelector(selector) {
      return selector === "[data-tpeu-frame]" ? iframe : null;
    },
    querySelectorAll(selector) {
      return selector === "[data-tpeu-state]" ? states : [];
    },
    states,
  };
};

const first = createWrapper("11111111-1111-4111-8111-111111111111");
const second = createWrapper("22222222-2222-4222-8222-222222222222");
const rootDocument = {
  documentElement: {},
  querySelectorAll(selector) {
    return selector === "[data-tpeu-embed]" ? [first, second] : [];
  },
};
const hostWindow = {
  IntersectionObserver: FakeIntersectionObserver,
  MutationObserver: FakeMutationObserver,
};
const controllers = [];
const createController = (options) => {
  const controller = {
    options,
    requested: 0,
    destroyed: false,
    markRequested() {
      this.requested += 1;
    },
    destroy() {
      this.destroyed = true;
    },
  };
  controllers.push(controller);
  return controller;
};

const frontend = createTurnierplanFrontend({
  hostWindow,
  rootDocument,
  createController,
});
frontend.start();

assert.equal(controllers.length, 2, "all initial embeds should be initialized");
assert.equal(FakeIntersectionObserver.instances.length, 2);
assert.equal(FakeIntersectionObserver.instances[0].options.rootMargin, "300px");
assert.deepEqual(FakeIntersectionObserver.instances[0].observed, [first.iframe]);

FakeIntersectionObserver.instances[0].intersect();
assert.equal(controllers[0].requested, 1, "lazy request starts near the viewport");
assert.equal(FakeIntersectionObserver.instances[0].disconnected, true);

controllers[0].options.onReady();
assert.equal(first.dataset.tpeuStatus, "ready");
assert.equal(first.states.find(({ dataset }) => dataset.tpeuState === "ready").hidden, false);
assert.equal(first.states.find(({ dataset }) => dataset.tpeuState === "loading").hidden, true);

const dynamic = createWrapper("33333333-3333-4333-8333-333333333333");
FakeMutationObserver.instance.trigger({ addedNodes: [dynamic], removedNodes: [] });
assert.equal(controllers.length, 3, "a dynamically inserted embed should initialize");

frontend.initialize(dynamic);
assert.equal(controllers.length, 3, "an embed should initialize only once");

FakeMutationObserver.instance.trigger({ addedNodes: [], removedNodes: [dynamic] });
assert.equal(controllers[2].destroyed, true, "removed embeds should release listeners");
assert.equal(dynamic.dataset.tpeuInitialized, undefined);

frontend.destroy();
assert.equal(controllers[0].destroyed, true);
assert.equal(controllers[1].destroyed, true);
assert.equal(FakeMutationObserver.instance.disconnected, true);

process.stdout.write("Embed frontend tests passed.\n");
