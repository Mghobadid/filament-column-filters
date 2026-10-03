import jalaali from 'jalaali-js'

const { toJalaali, toGregorian, jalaaliMonthLength } = jalaali

export function jalaliDate(year, month, day) {
    const { gy, gm, gd } = toGregorian(year, month, day)
    return new Date(gy, gm - 1, gd)
}

function pad(number) {
    return String(number).padStart(2, '0')
}

function formatDate(date) {
    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`
}

function startOfWeek(date, weekStartsOn) {
    const result = new Date(date)
    const diff = (result.getDay() - weekStartsOn + 7) % 7
    result.setDate(result.getDate() - diff)

    return result
}

function addDays(date, days) {
    const result = new Date(date)
    result.setDate(result.getDate() + days)

    return result
}

export function presetRange(preset, weekStartsOn = 0, jalali = false) {
    const today = new Date()
    today.setHours(0, 0, 0, 0)

    if (jalali && ['this_month', 'last_month', 'this_year', 'last_year'].includes(preset)) {
        let { jy, jm } = toJalaali(today)
        if (preset === 'last_month' && --jm === 0) { jm = 12; jy-- }
        if (preset === 'last_year') jy--
        if (preset.endsWith('year')) {
            return [jalaliDate(jy, 1, 1), jalaliDate(jy, 12, jalaaliMonthLength(jy, 12))]
        }
        return [jalaliDate(jy, jm, 1), jalaliDate(jy, jm, jalaaliMonthLength(jy, jm))]
    }

    switch (preset) {
        case 'today':
            return [today, today]
        case 'yesterday': {
            const yesterday = addDays(today, -1)

            return [yesterday, yesterday]
        }
        case 'this_week': {
            const start = startOfWeek(today, weekStartsOn)

            return [start, addDays(start, 6)]
        }
        case 'last_week': {
            const start = addDays(startOfWeek(today, weekStartsOn), -7)

            return [start, addDays(start, 6)]
        }
        case 'this_month': {
            const start = new Date(today.getFullYear(), today.getMonth(), 1)

            return [start, new Date(today.getFullYear(), today.getMonth() + 1, 0)]
        }
        case 'last_month': {
            const start = new Date(today.getFullYear(), today.getMonth() - 1, 1)

            return [start, new Date(today.getFullYear(), today.getMonth(), 0)]
        }
        case 'last_7_days':
            return [addDays(today, -6), today]
        case 'last_30_days':
            return [addDays(today, -29), today]
        case 'this_year': {
            const start = new Date(today.getFullYear(), 0, 1)

            return [start, new Date(today.getFullYear(), 11, 31)]
        }
        case 'last_year': {
            const start = new Date(today.getFullYear() - 1, 0, 1)

            return [start, new Date(today.getFullYear() - 1, 11, 31)]
        }
        default:
            return [null, null]
    }
}

// Only one popup may be open at a time, across all columns and tables on the
// page — opening one closes the currently open one.
let openInstance = null

export default function filamentColumnFilters(config) {
    return {
        open: false,

        state: {},

        optionSearch: '',
        remoteOptions: null,
        knownOptions: [],
        isSearching: false,
        searchError: false,
        searchTimer: null,
        searchVersion: 0,
        popupVersion: 0,

        rememberOptions(options) {
            const selected = new Set(config.multiple ? this.state.values ?? [] : [this.state.value])
            const merged = new Map([...(config.options ?? []), ...this.knownOptions.filter((option) => selected.has(String(option.value)))]
                .map((option) => [String(option.value), option]))
            options.forEach((option) => merged.set(String(option.value), option))
            this.knownOptions = [...merged.values()]
        },

        get displayedOptions() {
            const options = this.remoteOptions ?? (config.options ?? []).filter((option) => this.optionMatches(option.label))
            if (!config.remoteSearch) return options
            const selected = new Set(config.multiple ? this.state.values ?? [] : [this.state.value])
            const merged = new Map(options.map((option) => [String(option.value), option]))
            this.knownOptions.filter((option) => selected.has(String(option.value)))
                .forEach((option) => merged.set(String(option.value), option))
            return [...merged.values()]
        },

        searchOptions() {
            clearTimeout(this.searchTimer)
            const version = ++this.searchVersion
            this.rememberOptions(this.remoteOptions ?? [])
            this.remoteOptions = null
            this.searchError = false
            const search = this.optionSearch.trim()
            this.isSearching = Boolean(config.remoteSearch && search)
            if (!this.isSearching) return
            this.searchTimer = setTimeout(() => this.fetchOptions(search, version), config.searchDebounce ?? 500)
        },

        async fetchOptions(search, version) {
            try {
                const options = await this.$wire.callSchemaComponentMethod(config.remoteComponentKey, 'search', [search])
                if (!Array.isArray(options)) throw new Error('Remote select component unavailable')
                if (version !== this.searchVersion) return
                this.remoteOptions = options
                this.rememberOptions(options)
            } catch (error) {
                if (version !== this.searchVersion) return
                this.searchError = true
            } finally {
                if (version === this.searchVersion) {
                    this.isSearching = false
                    this.$nextTick(() => this.position())
                }
            }
        },

        async loadSelectedOptions() {
            const version = this.popupVersion
            try {
                const options = await this.$wire.callSchemaComponentMethod(config.remoteComponentKey, 'selectedOptions')
                if (!Array.isArray(options)) throw new Error('Remote select component unavailable')
                if (version === this.popupVersion) this.rememberOptions(options)
            } catch (error) {
                if (version === this.popupVersion) this.searchError = true
            }
        },

        destroy() {
            this.popupVersion++
            clearTimeout(this.searchTimer)
            this.searchVersion++
            if (openInstance === this) openInstance = null
        },

        panelStyle: {},

        calendarField: null,
        calendarYear: 1400,
        calendarMonth: 1,

        displayDate(value) {
            if (!value) return ''
            const [year, month, day] = value.slice(0, 10).split('-').map(Number)
            const { jy, jm, jd } = toJalaali(year, month, day)
            return `${jy}/${pad(jm)}/${pad(jd)}`
        },

        showCalendar(field) {
            const value = this.state[field]
            const date = value ? new Date(...value.slice(0, 10).split('-').map((v, i) => Number(v) - (i === 1 ? 1 : 0))) : new Date()
            const { jy, jm } = toJalaali(date)
            this.calendarYear = jy
            this.calendarMonth = jm
            this.calendarField = field
            this.$nextTick(() => this.position())
        },

        moveMonth(offset) {
            const month = this.calendarMonth - 1 + offset
            const year = this.calendarYear + Math.floor(month / 12)
            if (year < 1 || year > 3177) return
            this.calendarYear = year
            this.calendarMonth = ((month % 12) + 12) % 12 + 1
        },

        setCalendarYear(input) {
            const normalized = String(input).replace(/[۰-۹]/g, (digit) => '۰۱۲۳۴۵۶۷۸۹'.indexOf(digit))
                .replace(/[٠-٩]/g, (digit) => '٠١٢٣٤٥٦٧٨٩'.indexOf(digit))
            const year = Number(normalized)
            if (Number.isInteger(year) && year >= 1 && year <= 3177) this.calendarYear = year
            return this.calendarYear
        },

        get calendarMonths() {
            const formatter = new Intl.DateTimeFormat(config.locale?.replace('_', '-') || 'en', { calendar: 'persian', month: 'long' })
            return Array.from({ length: 12 }, (_, i) => ({ value: i + 1, label: formatter.format(jalaliDate(1400, i + 1, 1)) }))
        },

        get calendarTitle() {
            return new Intl.DateTimeFormat(config.locale?.replace('_', '-') || 'en', {
                calendar: 'persian', month: 'long', year: 'numeric',
            }).format(jalaliDate(this.calendarYear, this.calendarMonth, 1))
        },

        get weekdays() {
            return Array.from({ length: 7 }, (_, i) => new Intl.DateTimeFormat(config.locale?.replace('_', '-') || 'en', { weekday: 'short' })
                .format(new Date(2024, 0, 7 + ((config.weekStartsOn ?? 0) + i) % 7)))
        },

        get calendarDays() {
            const start = jalaliDate(this.calendarYear, this.calendarMonth, 1)
            const offset = (start.getDay() - (config.weekStartsOn ?? 0) + 7) % 7
            return Array.from({ length: offset + jalaaliMonthLength(this.calendarYear, this.calendarMonth) }, (_, i) => {
                const day = i - offset + 1
                return { day: day > 0 ? day : null, value: day > 0 ? formatDate(jalaliDate(this.calendarYear, this.calendarMonth, day)) : null }
            })
        },

        selectDay(value) {
            this.state[this.calendarField] = value
            this.calendarField = null
            this.$nextTick(() => this.position())
        },

        init() {
            this.rememberOptions(config.options ?? [])
            this.resetLocalState()
        },

        get wirePath() {
            return `tableFilters.${config.filterName}`
        },

        currentWireState() {
            let current = null

            try {
                current = this.$wire.get(this.wirePath)
            } catch (error) {
                current = null
            }

            return current && typeof current === 'object' ? current : {}
        },

        resetLocalState() {
            const current = this.currentWireState()

            if (config.type === 'search') {
                this.state = {
                    value: current[config.fields.value] ?? '',
                }
            } else if (config.type === 'date' || config.type === 'range') {
                this.state = {
                    from: current[config.fields.from] ?? null,
                    until: current[config.fields.until] ?? null,
                }
            } else if (config.type === 'select') {
                if (config.multiple) {
                    const values = current[config.fields.value]

                    this.state = {
                        values: Array.isArray(values) ? values.map(String) : [],
                    }
                } else {
                    const value = current[config.fields.value]

                    this.state = {
                        value: value === null || value === undefined || value === '' ? null : String(value),
                    }
                }
            }
        },

        toggle() {
            this.open ? this.close() : this.openPanel()
        },

        openPanel() {
            if (openInstance && openInstance !== this) {
                openInstance.close()
            }

            openInstance = this

            this.resetLocalState()
            this.optionSearch = ''
            this.remoteOptions = null
            this.searchError = false
            this.open = true
            if (config.remoteSearch) this.loadSelectedOptions()

            this.$nextTick(() => {
                this.position()

                // The positioning style lands asynchronously, so prevent the
                // focus from scrolling the page toward the panel's
                // pre-positioned location.
                if (config.type === 'search' && this.$refs.searchInput) {
                    this.$refs.searchInput.focus({ preventScroll: true })
                } else if (config.type === 'select' && this.$refs.optionSearchInput) {
                    this.$refs.optionSearchInput.focus({ preventScroll: true })
                } else if (config.type === 'date' && this.$refs.fromDateInput) {
                    this.$refs.fromDateInput.focus({ preventScroll: true })
                } else if (config.type === 'range' && this.$refs.fromRangeInput) {
                    this.$refs.fromRangeInput.focus({ preventScroll: true })
                }
            })
        },

        close() {
            this.popupVersion++
            clearTimeout(this.searchTimer)
            this.searchVersion++
            this.isSearching = false
            this.calendarField = null
            if (openInstance === this) {
                openInstance = null
            }

            this.open = false
        },

        optionMatches(label) {
            const search = this.optionSearch.trim().toLowerCase()

            return search === '' || String(label).toLowerCase().includes(search)
        },

        get hasVisibleOptions() {
            return this.displayedOptions.length > 0
        },

        visibleOptionValues() {
            return this.displayedOptions
                .map((option) => String(option.value))
        },

        // Bulk selection applies to the options matching the current option
        // search — which is all of them when the search is empty.
        selectAll() {
            this.state.values = [...new Set([...(this.state.values ?? []), ...this.visibleOptionValues()])]
        },

        deselectAll() {
            const visible = new Set(this.visibleOptionValues())

            this.state.values = (this.state.values ?? []).filter((value) => ! visible.has(value))
        },

        position() {
            const trigger = this.$refs.trigger

            if (! trigger) {
                return
            }

            const rect = trigger.getBoundingClientRect()
            const panel = this.$refs.panel
            const panelWidth = panel ? panel.offsetWidth : 288
            const panelHeight = panel ? panel.offsetHeight : 200
            const margin = 8

            let left = rect.left + rect.width / 2 - panelWidth / 2
            left = Math.min(Math.max(margin, left), window.innerWidth - panelWidth - margin)

            let top = rect.bottom + 6

            if (top + panelHeight > window.innerHeight - margin) {
                top = Math.max(margin, rect.top - panelHeight - 6)
            }

            this.panelStyle = {
                position: 'fixed',
                top: `${top}px`,
                left: `${left}px`,
            }
        },

        stateForWire() {
            const current = this.currentWireState()
            const next = { ...current }

            if (config.type === 'search') {
                next[config.fields.value] = this.state.value?.trim?.() ? this.state.value.trim() : null
            } else if (config.type === 'date' || config.type === 'range') {
                // The inputs hold strings, so '0' stays a valid value and only
                // empty input becomes null.
                next[config.fields.from] = this.state.from === null || this.state.from === undefined || this.state.from === '' ? null : this.state.from
                next[config.fields.until] = this.state.until === null || this.state.until === undefined || this.state.until === '' ? null : this.state.until
            } else if (config.type === 'select') {
                next[config.fields.value] = config.multiple
                    ? [...(this.state.values ?? [])]
                    : (this.state.value ?? null)
            }

            return next
        },

        apply() {
            this.close()

            // A live set triggers Livewire's `updatedTableFilters` hook, which
            // syncs deferred filter state and resets pagination.
            this.$wire.set(this.wirePath, this.stateForWire())
        },

        clear() {
            if (config.type === 'search') {
                this.state = { value: '' }
            } else if (config.type === 'date' || config.type === 'range') {
                this.state = { from: null, until: null }
            } else if (config.type === 'select') {
                this.state = config.multiple ? { values: [] } : { value: null }
            }

            this.apply()
        },

        applyPreset(preset) {
            const [from, until] = presetRange(preset, config.weekStartsOn ?? 0, config.jalali)

            this.state.from = from ? formatDate(from) : null
            this.state.until = until ? formatDate(until) : null
        },

        isPresetActive(preset) {
            const [from, until] = presetRange(preset, config.weekStartsOn ?? 0, config.jalali)

            return Boolean(
                from
                && until
                && this.state.from === formatDate(from)
                && this.state.until === formatDate(until),
            )
        },
    }
}
