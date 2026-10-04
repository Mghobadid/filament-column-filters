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

let openInstance = null

export function rangeInput(path, live) {
    const plain = value => String(value ?? '').replaceAll(',', '')
    return {
        display: '',
        init() {
            this.display = String(this.$wire.get(path) ?? '')
            this.$watch('display', value => {
                const number = plain(value)
                if (number !== plain(this.$wire.get(path))) {
                    this.$wire.set(path, number === '' ? null : number, live)
                }
            })
            this.$watch('$wire.' + path, value => {
                if (plain(value) !== plain(this.display)) {
                    this.display = String(value ?? '')
                }
            })
        },
    }
}

export default function filamentColumnFilters(config) {
    return {
        open: false,
        panelStyle: {},
        reposition: null,
        rangeInput,
        init() {},
        toggle() { this.open ? this.close() : this.openPanel() },
        openPanel() {
            if (openInstance && openInstance !== this) openInstance.close()
            openInstance = this
            this.open = true
            this.reposition = () => { if (this.open) this.position() }
            window.addEventListener('scroll', this.reposition, { capture: true, passive: true })
            window.addEventListener('resize', this.reposition)
            this.$nextTick(() => this.position())
        },
        close() {
            if (openInstance === this) openInstance = null
            this.open = false
            if (this.reposition) {
                window.removeEventListener('scroll', this.reposition, true)
                window.removeEventListener('resize', this.reposition)
                this.reposition = null
            }
        },
        destroy() { this.close() },
        position() {
            const trigger = document.getElementById(config.triggerId)
            if (!trigger) { this.close(); return }
            const rect = trigger.getBoundingClientRect()
            if (rect.bottom <= 0 || rect.top >= window.innerHeight || rect.right <= 0 || rect.left >= window.innerWidth) {
                this.close()
                return
            }
            const panel = this.$refs.panel
            const width = panel?.offsetWidth || 320
            const height = panel?.offsetHeight || 200
            const left = Math.max(8, Math.min(rect.left + rect.width / 2 - width / 2, window.innerWidth - width - 8))
            const top = rect.bottom + height + 14 > window.innerHeight ? Math.max(8, rect.top - height - 6) : rect.bottom + 6
            this.panelStyle = { position: 'fixed', left: left + 'px', top: top + 'px' }
        },
        async apply() {
            await this.$wire.callSchemaComponentMethod(config.componentKey, 'apply')
            this.close()
        },
        async clear() {
            await this.$wire.callSchemaComponentMethod(config.componentKey, 'resetFilter')
            this.close()
        },
        applyPreset(preset) {
            const [from, until] = presetRange(preset, config.weekStartsOn ?? 0, config.jalali)
            this.$wire.set(config.statePath + '.' + config.fields.from, from ? formatDate(from) : null, !config.deferred)
            this.$wire.set(config.statePath + '.' + config.fields.until, until ? formatDate(until) : null, !config.deferred)
        },
    }
}
