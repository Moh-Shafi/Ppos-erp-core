"""
Phase 10 — Load Testing Script
Run: k6 run tests/load/load_test.js
Requires: k6 (https://k6.io)
"""

import http from 'k6/http'
import { check, sleep } from 'k6'
import { Rate, Trend } from 'k6/metrics'

const BASE_URL = __ENV.BASE_URL || 'http://localhost:8000/api/v1'
const AUTH_EMAIL = __ENV.AUTH_EMAIL || 'admin@kasirpos.id'
const AUTH_PASSWORD = __ENV.AUTH_PASSWORD || 'KasirPOS2026!'

const errorRate = new Rate('errors')
const loginDuration = new Trend('login_duration')
const apiDuration = new Trend('api_duration')

export const options = {
  stages: [
    { duration: '2m', target: 50 },
    { duration: '5m', target: 100 },
    { duration: '10m', target: 200 },
    { duration: '3m', target: 0 },
  ],
  thresholds: {
    errors: ['rate<0.05'],
    http_req_duration: ['p(95)<500', 'p(99)<1000'],
    login_duration: ['p(95)<800'],
    api_duration: ['p(95)<300'],
  },
}

export function setup() {
  const loginRes = http.post(
    `${BASE_URL}/auth/login`,
    JSON.stringify({ email: AUTH_EMAIL, password: AUTH_PASSWORD }),
    { headers: { 'Content-Type': 'application/json' } }
  )

  if (loginRes.status !== 200 || loginRes.json('token') === undefined) {
    if (loginRes.json('2fa_required')) {
      console.log('2FA required for load test user — skipping auth')
      return { token: null }
    }
    throw new Error(`Login failed: ${loginRes.status} ${loginRes.body}`)
  }

  return { token: loginRes.json('token') }
}

export default function (data) {
  const headers = data.token
    ? { headers: { 'Content-Type': 'application/json', Authorization: `Bearer ${data.token}` } }
    : { headers: { 'Content-Type': 'application/json' } }

  // Test health endpoint
  const healthRes = http.get(`${BASE_URL}/health`, headers)
  check(healthRes, {
    'health status 200': (r) => r.status === 200,
    'health status healthy': (r) => r.json('status') === 'healthy',
  })

  if (data.token) {
    // Test authenticated endpoints
    const productsRes = http.get(`${BASE_URL}/products?per_page=10`, headers)
    check(productsRes, {
      'products status 200': (r) => r.status === 200,
    })
    apiDuration.add(productsRes.timings.duration)

    const dashboardRes = http.get(`${BASE_URL}/dashboard`, headers)
    check(dashboardRes, {
      'dashboard status 200': (r) => r.status === 200,
    })
    apiDuration.add(dashboardRes.timings.duration)

    // Test audit logs
    const auditRes = http.get(`${BASE_URL}/audit-logs?per_page=10`, headers)
    check(auditRes, {
      'audit logs status 200': (r) => r.status === 200,
    })
    apiDuration.add(auditRes.timings.duration)
  }

  errorRate.add(healthRes.status !== 200)
  sleep(1)
}

export function handleSummary(data) {
  return {
    'tests/load/results.json': JSON.stringify(data, null, 2),
  }
}
