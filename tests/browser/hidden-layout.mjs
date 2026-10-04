import assert from 'node:assert/strict'
import { chromium } from 'playwright'

// Run after publishing Testbench assets and starting tests/browser/server.php.
const browser = await chromium.launch({ channel: process.env.BROWSER_CHANNEL || 'chrome', headless: true })
const page = await browser.newPage()
const errors = []
page.on('pageerror', error => errors.push(error.message))
page.on('response', response => {
    if (response.status() >= 400) console.log('HTTP error', response.status(), response.url())
})
const base = process.env.TEST_URL || 'http://127.0.0.1:8765'
try {
    for (const live of [false, true]) {
        await page.goto(base + (live ? '/?live=1' : '/'))
        console.log('Checking hidden layout', live ? 'live' : 'deferred')
        await page.waitForFunction(() => window.Livewire && document.querySelectorAll('.fcf-trigger').length === 6)
        await page.waitForFunction(() => document.querySelectorAll('.fcf-panel').length === 6)
        assert.equal(await page.locator('.fi-ta-filters-dropdown').count(), 0)
        await page.locator('.fcf-trigger').first().click()
        const panel = page.locator('.fcf-panel--open')
        await panel.waitFor({ state: 'visible' })
        await panel.locator('input').fill('Ali')
        if (!live) {
            assert.equal(await page.locator('.fi-ta-row').count(), 2)
            await panel.getByRole('button', { name: 'Apply', exact: true }).click()
        }
        await page.waitForFunction(() => document.querySelectorAll('.fi-ta-row').length === 1)
        assert.match(await page.locator('.fi-ta-row').innerText(), /Alice/)
        await page.locator('.fi-ta-filter-indicators').waitFor({ state: 'visible' })
        if (!await panel.isVisible()) await page.locator('.fcf-trigger').first().click()
        await panel.getByRole('button', { name: 'Reset', exact: true }).click()
        await page.waitForFunction(() => document.querySelectorAll('.fi-ta-row').length === 2)
        assert.equal(await page.locator('.fi-ta-filter-indicators').count(), 0)
    }
    await page.goto(base + '/relationship')
    await page.waitForFunction(() => document.querySelectorAll('.fcf-panel').length === 2)
    await page.locator('.fcf-trigger').first().click()
    const relationship = page.locator('.fcf-panel--open')
    await relationship.locator('.fi-select-input-btn').click()
    const search = relationship.getByRole('textbox', { name: 'Search', exact: true })
    await search.fill('Zebra')
    await relationship.getByText('Zebra one', { exact: true }).waitFor({ state: 'visible' })
    await relationship.getByText('Zebra three', { exact: true }).waitFor({ state: 'visible' })
    assert.equal(await relationship.getByText('Zebra two', { exact: true }).count(), 0)
    await search.fill('Excluded')
    await relationship.locator('.fi-select-input-message').filter({ hasText: 'No options match your search.' }).waitFor({ state: 'visible' })
    assert.equal(await relationship.getByText('Excluded', { exact: true }).count(), 0)
    assert.deepEqual(errors, [])
    console.log('Hidden popup browser interactions passed: opening, deferred Apply, live filtering, indicators, reset.')
} finally {
    await browser.close()
}
