import { test } from 'node:test'
import assert from 'node:assert/strict'
import columnFilters, { jalaliDate, presetRange } from '../../resources/js/index.js'

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

test('calendar selection preserves mapped Gregorian state and unrelated fields', () => {
    const filter = columnFilters({ type: 'date', jalali: true, locale: 'fa', weekStartsOn: 6, filterName: 'created', fields: { from: 'start', until: 'end' } })
    filter.$wire = { get: () => ({ start: '2024-03-20', end: null, extra: 'preserved' }) }
    filter.$nextTick = () => {}
    filter.init()
    assert.equal(filter.displayDate(filter.state.from), '1403/01/01')
    filter.showCalendar('until')
    filter.calendarYear = 1403
    filter.calendarMonth = 1
    assert.equal(filter.calendarDays.filter((day) => day.day).length, 31)
    filter.selectDay('2024-04-19')
    assert.deepEqual(filter.stateForWire(), { start: '2024-03-20', end: '2024-04-19', extra: 'preserved' })
    filter.moveMonth(-1)
    assert.equal(filter.calendarYear, 1402)
    assert.equal(filter.calendarMonth, 12)
    assert.equal(filter.calendarDays.filter((day) => day.day).length, 29)
})
