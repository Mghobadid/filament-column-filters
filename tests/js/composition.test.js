import { test } from 'node:test'
import assert from 'node:assert/strict'
import popup from '../../resources/js/index.js'
import { rangeInput } from '../../resources/js/index.js'

for (const live of [true, false]) {
    test(`range display mask does not echo server updates (${live ? 'live' : 'deferred'})`, () => {
        const watchers = new Map()
        const calls = []
        let state = '3000000'
        const input = rangeInput('tableFilters.cf_price.from', live)
        input.$watch = (key, callback) => watchers.set(key, callback)
        input.$wire = {
            get: () => state,
            set: (path, value, immediate) => { state = value; calls.push([path, value, immediate]) },
        }
        input.init()
        // Mask formatting and a server response must not create requests.
        watchers.get('display')('3,000,000')
        assert.equal(calls.length, 0)
        input.display = '5,000,000'
        watchers.get('display')(input.display)
        assert.deepEqual(calls, [['tableFilters.cf_price.from', '5000000', live]])
        watchers.get('$wire.tableFilters.cf_price.from')('5000000')
        watchers.get('display')('5,000,000')
        assert.equal(calls.length, 1)
        state = null
        watchers.get('$wire.tableFilters.cf_price.from')(null)
        watchers.get('display')(input.display)
        assert.equal(input.display, '')
        assert.equal(calls.length, 1)
    })
}

test('popup follows document and nested scrolling, resizes, and removes listeners on destruction', () => {
    const originalWindow = globalThis.window
    const originalDocument = globalThis.document
    const listeners = new Map()
    let rect = { left: 400, right: 424, width: 24, top: 100, bottom: 124 }
    globalThis.window = {
        innerWidth: 1000, innerHeight: 800,
        addEventListener: (type, callback, options) => listeners.set(type, { callback, options }),
        removeEventListener: (type, callback) => {
            assert.equal(listeners.get(type).callback, callback)
            listeners.delete(type)
        },
    }
    globalThis.document = { getElementById: () => ({ getBoundingClientRect: () => rect }) }
    const filter = popup({ triggerId: 'header' })
    filter.$refs = { panel: { offsetWidth: 320, offsetHeight: 200 } }
    filter.$nextTick = callback => callback()
    try {
        filter.openPanel()
        assert.equal(filter.panelStyle.top, '130px')
        assert.equal(listeners.get('scroll').options.capture, true)
        rect = { ...rect, top: 50, bottom: 74, left: 300, right: 324 }
        listeners.get('scroll').callback()
        assert.equal(filter.panelStyle.top, '80px')
        assert.equal(filter.panelStyle.left, '152px')
        window.innerWidth = 450
        listeners.get('resize').callback()
        assert.equal(filter.panelStyle.left, '122px')
        rect = { ...rect, top: -30, bottom: -6 }
        listeners.get('scroll').callback()
        assert.equal(filter.open, false)
        assert.equal(listeners.size, 0)
        rect = { ...rect, top: 50, bottom: 74 }
        filter.openPanel()
        filter.destroy()
        assert.equal(listeners.size, 0)
    } finally {
        filter.close()
        globalThis.window = originalWindow
        globalThis.document = originalDocument
    }
})

test('validation failure keeps popup open without applying client state', async () => {
    const filter = popup({ componentKey: 'tableFiltersForm.created' })
    filter.open = true
    filter.$wire = { callSchemaComponentMethod: async () => { throw new Error('Invalid date range') } }
    await assert.rejects(filter.apply(), /Invalid date range/)
    assert.equal(filter.open, true)
})

test('successful apply closes popup and scopes the call to its schema', async () => {
    const calls = []
    const filter = popup({ componentKey: 'tableFiltersForm.created' })
    filter.open = true
    filter.$wire = { callSchemaComponentMethod: async (...args) => calls.push(args) }
    await filter.apply()
    assert.equal(filter.open, false)
    assert.deepEqual(calls, [['tableFiltersForm.created', 'apply']])
})

test('presets update mapped draft fields without applying unrelated filters', () => {
    const calls = []
    const filter = popup({ statePath: 'tableDeferredFilters.created', fields: { from: 'start', until: 'end' }, deferred: true })
    filter.$wire = { set: (...args) => calls.push(args) }
    filter.applyPreset('today')
    assert.equal(calls[0][0], 'tableDeferredFilters.created.start')
    assert.equal(calls[1][0], 'tableDeferredFilters.created.end')
    assert.equal(calls[0][1], calls[1][1])
    assert.equal(calls[0][2], false)
})
