const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const source = fs.readFileSync(path.join(__dirname, '../../js/advisor.js'), 'utf8');

function attach(clipboard) {
  const handlers = {};
  const button = { disabled: false, addEventListener: (name, fn) => { handlers[name] = fn; } };
  const text = { value: 'Original brief and scored snapshot', focus() { this.focused = true; }, select() { this.selected = true; } };
  const status = { textContent: '' };
  const preview = { open: false };
  const elements = {
    '[data-advisor-copy]': button,
    '[data-advisor-copy-text]': text,
    '[data-advisor-copy-status]': status,
    '[data-advisor-copy-preview]': preview,
  };
  const handoff = { querySelector: (selector) => elements[selector] };
  const result = { hidden: false };
  const field = {
    addEventListener(name, fn) { this[name] = fn; },
    closest: () => ({ querySelector: () => result }),
  };
  const Drupal = { behaviors: {}, t: (message) => message };
  vm.runInNewContext(source, {
    Drupal,
    navigator: { clipboard },
    once: (id) => id === 'advisor-handoff' ? [handoff] : (id === 'advisor-brief' ? [field] : []),
  });
  Drupal.behaviors.aiSiteAdvisor.attach({});
  return { handlers, button, text, status, preview, field, result };
}

test('copies the displayed snapshot and restores the button after success', async () => {
  let copied;
  let finish;
  const ui = attach({ writeText: (value) => { copied = value; return new Promise((resolve) => { finish = resolve; }); } });
  const pending = ui.handlers.click();
  assert.equal(copied, ui.text.value);
  assert.equal(ui.button.disabled, true);
  finish();
  await pending;
  assert.equal(ui.button.disabled, false);
  assert.match(ui.status.textContent, /Plan copied/);
  assert.equal(ui.preview.open, false);
});

test('clipboard rejection or absence selects a usable manual fallback', async () => {
  for (const clipboard of [undefined, { writeText: async () => { throw new Error('Denied'); } }]) {
    const ui = attach(clipboard);
    await ui.handlers.click();
    assert.equal(ui.preview.open, true);
    assert.equal(ui.text.focused, true);
    assert.equal(ui.text.selected, true);
    assert.equal(ui.button.disabled, false);
    assert.match(ui.status.textContent, /manually/);
    assert.equal(ui.text.value, 'Original brief and scored snapshot');
  }
});

test('editing the brief hides the previous result and its copy handoff', () => {
  const ui = attach({});
  ui.field.input();
  assert.equal(ui.result.hidden, true);
});
