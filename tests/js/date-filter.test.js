import { test } from 'node:test'
import assert from 'node:assert/strict'
import { jalaliDate, presetRange } from '../../resources/js/index.js'

const iso = (date) => [date.getFullYear(), String(date.getMonth() + 1).padStart(2, '0'), String(date.getDate()).padStart(2, '0')].join('-')


test('Nowruz conversion and leap Esfand', () => {
    assert.equal(iso(jalaliDate(1403, 1, 1)), '2024-03-20')
    assert.equal(iso(jalaliDate(1399, 12, 30)), '2021-03-20')
})
test('Jalali presets use Persian month and year boundaries', (t) => {
    t.mock.timers.enable({ apis: ['Date'], now: new Date(2024, 2, 25).getTime() })
    assert.deepEqual(presetRange('this_month', 6, true).map(iso), ['2024-03-20', '2024-04-19'])
    assert.deepEqual(presetRange('last_month', 6, true).map(iso), ['2024-02-20', '2024-03-19'])
    assert.deepEqual(presetRange('this_year', 6, true).map(iso), ['2024-03-20', '2025-03-20'])
    assert.deepEqual(presetRange('last_year', 6, true).map(iso), ['2023-03-21', '2024-03-19'])
    assert.deepEqual(presetRange('this_week', 6, true).map(iso), ['2024-03-23', '2024-03-29'])
    assert.deepEqual(presetRange('this_month').map(iso), ['2024-03-01', '2024-03-31'])
})
