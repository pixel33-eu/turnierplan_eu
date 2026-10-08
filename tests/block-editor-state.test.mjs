import assert from "node:assert/strict";
import { readFile } from "node:fs/promises";

const source = await readFile(
  new URL("../assets/src/block-editor-state.js", import.meta.url),
  "utf8",
);
const moduleUrl = `data:text/javascript;base64,${Buffer.from(source).toString("base64")}`;
const state = await import(moduleUrl);

const metadata = {
  branding: { policy: "required", default_visible: true },
  groups: [
    { id: "grp_one", label: "One" },
    { id: "grp_two", label: "Two" },
  ],
  participants: [
    { id: "ptc_one", label: "One", group_ids: ["grp_one"] },
    { id: "ptc_two", label: "Two", group_ids: ["grp_two"] },
  ],
  tournament: { ref: "trn_canonical" },
  views: [
    { id: "standings", filters: ["group"], options: ["points"] },
    {
      id: "matches",
      filters: ["group", "participant", "match_number_range", "date_range"],
      options: ["time"],
    },
  ],
};
const config = {
  tournamentRef: "alias",
  view: "matches",
  group: "grp_one",
  participant: "ptc_one",
  matchFrom: 2,
  matchTo: 5,
  dateFrom: "2026-09-01",
  dateTo: "2026-09-02",
  showBranding: false,
};

const standings = state.changeView(config, "standings", metadata);
assert.equal(standings.group, "grp_one", "a shared group should survive a view switch");
assert.equal(standings.participant, null);
assert.equal(standings.matchFrom, null);
assert.equal(standings.dateFrom, null);
assert.deepEqual(state.configurationWarnings(config, metadata), []);
assert.deepEqual(
  state.configurationWarnings({ ...config, view: "bracket" }, metadata),
  ["view"],
);
assert.deepEqual(
  state.configurationWarnings(
    { ...config, group: "grp_missing", participant: "ptc_one" },
    metadata,
  ),
  ["group"],
);

const wrongGroup = state.changeView(
  { ...config, group: "grp_two", participant: "ptc_one" },
  "matches",
  metadata,
);
assert.equal(wrongGroup.participant, null, "incompatible participants should reset");
assert.deepEqual(
  state.participantsForGroup(metadata, "grp_two").map(({ id }) => id),
  ["ptc_two"],
);

const reconciled = state.reconcileMetadata(config, metadata);
assert.equal(reconciled.tournamentRef, "trn_canonical");
assert.equal(reconciled.showBranding, true, "required branding should win");

const firstPreset = { id: 4, config: { tournamentRef: "first" } };
const secondPreset = { id: 9, config: { tournamentRef: "second" } };
assert.equal(
  state.inlineConfigForPreset([firstPreset, secondPreset], 9, config),
  secondPreset.config,
  "switching to inline should copy the exact selected preset",
);
assert.equal(
  state.inlineConfigForPreset([firstPreset, secondPreset], 99, config),
  config,
  "a missing preset must not fall back to a different preset",
);
assert.match(
  state.formatMetadataTimestamp("2026-10-08T12:30:00Z", "en-GB"),
  /2026/,
  "metadata timestamps should use a localized date and time formatter",
);
assert.equal(state.formatMetadataTimestamp("invalid", "en-GB"), "invalid");

let reduced = state.metadataReducer(state.initialMetadataState, { type: "loading" });
assert.equal(reduced.status, "loading");
reduced = state.metadataReducer(reduced, {
  type: "success",
  metadata,
  stale: true,
});
assert.equal(reduced.status, "stale");
assert.equal(reduced.metadata, metadata);
reduced = state.metadataReducer(reduced, {
  type: "loading",
});
assert.equal(reduced.status, "refreshing", "loaded data should remain during refresh");
reduced = state.metadataReducer(reduced, {
  type: "error",
  error: new Error("offline"),
});
assert.equal(reduced.status, "error");
assert.equal(reduced.metadata, metadata, "an update error should retain prior metadata");

class FakeAbortController {
  constructor() {
    this.signal = { aborted: false };
  }

  abort() {
    this.signal.aborted = true;
  }
}

const pending = [];
const loader = state.createMetadataLoader(
  (options) =>
    new Promise((resolve) => {
      pending.push({ options, resolve });
    }),
  FakeAbortController,
);
const first = loader.load({ path: "/first" });
const second = loader.load({ path: "/second" });
assert.equal(pending[0].options.signal.aborted, true, "a newer request should abort the old one");
pending[0].resolve({ old: true });
pending[1].resolve({ current: true });
assert.deepEqual(await first, { status: "ignored" });
assert.deepEqual(await second, { data: { current: true }, status: "success" });

process.stdout.write("Block editor state tests passed.\n");
