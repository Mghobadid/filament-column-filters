import { test } from 'node:test'
import assert from 'node:assert/strict'
import columnFilters from '../../resources/js/index.js'

function makeFilter() {
    const filter = columnFilters({ type: 'select', remoteSearch: true, multiple: true, columnName: 'author_id', filterName: 'authors', fields: { value: 'values' }, options: [{ value: '1', label: 'Alice' }] })
    filter.$wire = { get: () => ({ values: [] }) }
    filter.$nextTick = () => {}
    filter.init()
    return filter
}

test('local matches never prevent remote search and remote labels need not match search text', async () => {
    const filter = makeFilter()
    filter.optionSearch = 'Ali'
    assert.deepEqual(filter.visibleOptionValues(), ['1'])
    filter.$wire.searchColumnFilterOptions = async () => [{ value: '2', label: 'Bob (matched email)' }]
    await filter.fetchOptions('Ali', filter.searchVersion)
    assert.deepEqual(filter.visibleOptionValues(), ['2'])
    filter.state.values = ['2']
    filter.optionSearch = ''
    filter.searchOptions()
    assert.deepEqual(filter.visibleOptionValues(), ['1', '2'])
})

test('stale responses and closed popup responses are ignored', async () => {
    const filter = makeFilter()
    let resolve
    filter.$wire.searchColumnFilterOptions = () => new Promise((done) => { resolve = done })
    const pending = filter.fetchOptions('old', filter.searchVersion)
    filter.close()
    resolve([{ value: '2', label: 'Old' }])
    await pending
    assert.equal(filter.remoteOptions, null)
})

test('remote failure leaves local matches usable and clears loading state', async () => {
    const filter = makeFilter()
    filter.isSearching = true
    filter.$wire.searchColumnFilterOptions = async () => { throw new Error('network') }
    await filter.fetchOptions('Ali', filter.searchVersion)
    assert.equal(filter.searchError, true)
    assert.equal(filter.isSearching, false)
    assert.deepEqual(filter.visibleOptionValues(), ['1'])
})
