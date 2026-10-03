import { test } from 'node:test'
import assert from 'node:assert/strict'
import popup from '../../resources/js/index.js'

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
