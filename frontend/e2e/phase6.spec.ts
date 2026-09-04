import { test, expect } from '@playwright/test'

async function getAuthToken(request: any): Promise<string> {
  const loginRes = await request.post('/api/v1/auth/login', {
    data: { email: 'e2e.owner@test.com', password: 'password123' },
  })
  expect(loginRes.ok()).toBeTruthy()
  const loginBody = await loginRes.json()
  return loginBody.token
}

test.describe('Phase 6 - Finance / Accounting', () => {
  const baseUrl = '/api/v1'

  test('Chart of Accounts returns default accounts', async ({ request }) => {
    const token = await getAuthToken(request)
    const response = await request.get(`${baseUrl}/finance/accounts?flat=1`, {
      headers: { Authorization: `Bearer ${token}` },
    })
    expect(response.ok()).toBeTruthy()
    const body = await response.json()
    expect(body).toEqual(expect.arrayContaining([expect.objectContaining({ code: '1-1000' })]))
  })

  test('Manual journal entry is posted and balanced', async ({ request }) => {
    const token = await getAuthToken(request)
    const accountsRes = await request.get(`${baseUrl}/finance/accounts?flat=1`, {
      headers: { Authorization: `Bearer ${token}` },
    })
    expect(accountsRes.ok()).toBeTruthy()
    const accounts = await accountsRes.json()
    const cash = accounts.find((a: any) => a.code === '1-1000')
    const revenue = accounts.find((a: any) => a.code === '4-1000')

    const response = await request.post(`${baseUrl}/finance/journal-entries`, {
      headers: { Authorization: `Bearer ${token}` },
      data: {
        entry_date: new Date().toISOString().slice(0, 10),
        description: 'E2E manual journal',
        lines: [
          { account_id: cash.id, debit: 100000, credit: 0 },
          { account_id: revenue.id, debit: 0, credit: 100000 },
        ],
      },
    })
    expect(response.ok()).toBeTruthy()
    const body = await response.json()
    expect(body.data.total_debit).toBe('100000.00')
    expect(body.data.total_credit).toBe('100000.00')
  })

  test('Trial Balance is balanced after posting', async ({ request }) => {
    const token = await getAuthToken(request)
    const accountsRes = await request.get(`${baseUrl}/finance/accounts?flat=1`, {
      headers: { Authorization: `Bearer ${token}` },
    })
    expect(accountsRes.ok()).toBeTruthy()
    const accounts = await accountsRes.json()
    const cash = accounts.find((a: any) => a.code === '1-1000')
    const revenue = accounts.find((a: any) => a.code === '4-1000')

    await request.post(`${baseUrl}/finance/journal-entries`, {
      headers: { Authorization: `Bearer ${token}` },
      data: {
        entry_date: new Date().toISOString().slice(0, 10),
        lines: [
          { account_id: cash.id, debit: 50000, credit: 0 },
          { account_id: revenue.id, debit: 0, credit: 50000 },
        ],
      },
    })

    const now = new Date().toISOString().slice(0, 10)
    const reportRes = await request.get(`${baseUrl}/finance/reports/trial-balance?start_date=${now}&end_date=${now}`, {
      headers: { Authorization: `Bearer ${token}` },
    })
    expect(reportRes.ok()).toBeTruthy()
    const report = await reportRes.json()
    expect(report.is_balanced).toBe(true)
  })

  test('Profit & Loss and Balance Sheet endpoints return data', async ({ request }) => {
    const token = await getAuthToken(request)
    const now = new Date().toISOString().slice(0, 10)

    const plRes = await request.get(`${baseUrl}/finance/reports/profit-loss?start_date=${now}&end_date=${now}`, {
      headers: { Authorization: `Bearer ${token}` },
    })
    expect(plRes.ok()).toBeTruthy()
    const pl = await plRes.json()
    expect(typeof pl.net_income).toBe('number')

    const bsRes = await request.get(`${baseUrl}/finance/reports/balance-sheet?as_of=${now}`, {
      headers: { Authorization: `Bearer ${token}` },
    })
    expect(bsRes.ok()).toBeTruthy()
    const bs = await bsRes.json()
    expect(typeof bs.assets).toBe('number')
    expect(typeof bs.liabilities).toBe('number')
    expect(typeof bs.equity).toBe('number')
  })
})
