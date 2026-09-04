import { useState, useEffect, useCallback } from 'react'
import { DashboardLayout } from '@/layouts/DashboardLayout'
import { Card, CardHeader, CardTitle, CardContent } from '@/components/ui/Card'
import { securityService, type TwoFactorStatus, type TwoFactorEnableResponse } from '@/services/security'

export function SecuritySettingsPage() {
  const [status, setStatus] = useState<TwoFactorStatus | null>(null)
  const [enableData, setEnableData] = useState<TwoFactorEnableResponse | null>(null)
  const [verifyCode, setVerifyCode] = useState('')
  const [disableCode, setDisableCode] = useState('')
  const [regenCode, setRegenCode] = useState('')
  const [loading, setLoading] = useState(false)
  const [error, setError] = useState('')
  const [success, setSuccess] = useState('')
  const [regenBackupCodes, setRegenBackupCodes] = useState<string[] | null>(null)

  const fetchStatus = useCallback(async () => {
    try {
      const s = await securityService.get2faStatus()
      setStatus(s)
    } catch {
      // ignore
    }
  }, [])

  useEffect(() => {
    fetchStatus()
  }, [fetchStatus])

  const handleEnable = async () => {
    setLoading(true)
    setError('')
    setSuccess('')
    try {
      const data = await securityService.enable2fa()
      setEnableData(data)
    } catch {
      setError('Failed to enable 2FA')
    } finally {
      setLoading(false)
    }
  }

  const handleVerify = async () => {
    setLoading(true)
    setError('')
    setSuccess('')
    try {
      await securityService.verify2fa(verifyCode)
      setSuccess('2FA verified and enabled successfully!')
      setVerifyCode('')
      setEnableData(null)
      await fetchStatus()
    } catch {
      setError('Invalid verification code')
    } finally {
      setLoading(false)
    }
  }

  const handleDisable = async () => {
    setLoading(true)
    setError('')
    setSuccess('')
    try {
      await securityService.disable2fa(disableCode)
      setSuccess('2FA disabled successfully!')
      setDisableCode('')
      await fetchStatus()
    } catch {
      setError('Invalid code. Could not disable 2FA.')
    } finally {
      setLoading(false)
    }
  }

  const handleRegenerate = async () => {
    setLoading(true)
    setError('')
    setSuccess('')
    try {
      const res = await securityService.regenerateBackupCodes(regenCode)
      setRegenBackupCodes(res.backup_codes)
      setRegenCode('')
      setSuccess('Backup codes regenerated!')
      await fetchStatus()
    } catch {
      setError('Invalid code. Could not regenerate backup codes.')
    } finally {
      setLoading(false)
    }
  }

  return (
    <DashboardLayout>
      <div className="max-w-2xl space-y-6">
        <div>
          <h1 className="text-2xl font-bold text-foreground">Keamanan</h1>
          <p className="text-muted-foreground">Manage two-factor authentication and security settings</p>
        </div>

        {error && (
          <div className="rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-700">{error}</div>
        )}
        {success && (
          <div className="rounded-lg border border-green-200 bg-green-50 p-3 text-sm text-green-700">{success}</div>
        )}

        {/* 2FA Status */}
        <Card>
          <CardHeader>
            <CardTitle>Two-Factor Authentication (2FA)</CardTitle>
          </CardHeader>
          <CardContent className="space-y-4">
            {status && (
              <div className="flex items-center gap-3">
                <span className={`inline-flex h-3 w-3 rounded-full ${status.enabled ? 'bg-green-500' : 'bg-gray-300'}`} />
                <div>
                  <p className="text-sm font-medium text-foreground">
                    {status.enabled ? 'Enabled' : 'Not enabled'}
                  </p>
                  {status.enabled_at && (
                    <p className="text-xs text-muted-foreground">Enabled: {new Date(status.enabled_at).toLocaleString()}</p>
                  )}
                  {status.last_used_at && (
                    <p className="text-xs text-muted-foreground">Last used: {new Date(status.last_used_at).toLocaleString()}</p>
                  )}
                  <p className="text-xs text-muted-foreground">Backup codes remaining: {status.backup_codes_remaining}</p>
                </div>
              </div>
            )}

            {!status?.enabled && !enableData && (
              <button
                onClick={handleEnable}
                disabled={loading}
                className="rounded-lg bg-[#f54927] px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-[#e03e1e] disabled:opacity-50"
              >
                {loading ? 'Processing...' : 'Enable 2FA'}
              </button>
            )}
          </CardContent>
        </Card>

        {/* Enable 2FA - QR Code & Backup Codes */}
        {enableData && (
          <Card>
            <CardHeader>
              <CardTitle>Scan QR Code & Save Backup Codes</CardTitle>
            </CardHeader>
            <CardContent className="space-y-4">
              <div>
                <p className="mb-2 text-sm font-medium text-foreground">1. Scan this QR code with your authenticator app:</p>
                <div className="rounded-lg border bg-white p-4">
                  <p className="font-mono text-xs break-all text-gray-600">{enableData.qr_code}</p>
                </div>
                <p className="mt-2 text-xs text-muted-foreground">Or manually enter this secret: <span className="font-mono font-bold">{enableData.secret}</span></p>
              </div>

              <div>
                <p className="mb-2 text-sm font-medium text-foreground">2. Save these backup codes in a safe place:</p>
                <div className="grid grid-cols-2 gap-2 rounded-lg border bg-gray-50 p-3">
                  {enableData.backup_codes.map((code, i) => (
                    <p key={i} className="font-mono text-sm font-bold text-gray-700">{code}</p>
                  ))}
                </div>
              </div>

              <div>
                <p className="mb-2 text-sm font-medium text-foreground">3. Enter the 6-digit code from your app to verify:</p>
                <div className="flex gap-2">
                  <input
                    type="text"
                    value={verifyCode}
                    onChange={(e) => setVerifyCode(e.target.value)}
                    maxLength={6}
                    placeholder="000000"
                    className="w-32 rounded-lg border px-3 py-2 text-center font-mono text-lg tracking-widest"
                  />
                  <button
                    onClick={handleVerify}
                    disabled={loading || verifyCode.length !== 6}
                    className="rounded-lg bg-[#f54927] px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-[#e03e1e] disabled:opacity-50"
                  >
                    Verify
                  </button>
                </div>
              </div>
            </CardContent>
          </Card>
        )}

        {/* Disable 2FA */}
        {status?.enabled && (
          <Card>
            <CardHeader>
              <CardTitle>Disable 2FA</CardTitle>
            </CardHeader>
            <CardContent className="space-y-4">
              <p className="text-sm text-muted-foreground">Enter your current 6-digit code to disable 2FA.</p>
              <div className="flex gap-2">
                <input
                  type="text"
                  value={disableCode}
                  onChange={(e) => setDisableCode(e.target.value)}
                  maxLength={6}
                  placeholder="000000"
                  className="w-32 rounded-lg border px-3 py-2 text-center font-mono text-lg tracking-widest"
                />
                <button
                  onClick={handleDisable}
                  disabled={loading || disableCode.length !== 6}
                  className="rounded-lg border border-red-300 px-4 py-2 text-sm font-medium text-red-600 transition-colors hover:bg-red-50 disabled:opacity-50"
                >
                  Disable
                </button>
              </div>
            </CardContent>
          </Card>
        )}

        {/* Regenerate Backup Codes */}
        {status?.enabled && (
          <Card>
            <CardHeader>
              <CardTitle>Regenerate Backup Codes</CardTitle>
            </CardHeader>
            <CardContent className="space-y-4">
              <p className="text-sm text-muted-foreground">Enter your current 6-digit code to generate new backup codes.</p>
              <div className="flex gap-2">
                <input
                  type="text"
                  value={regenCode}
                  onChange={(e) => setRegenCode(e.target.value)}
                  maxLength={6}
                  placeholder="000000"
                  className="w-32 rounded-lg border px-3 py-2 text-center font-mono text-lg tracking-widest"
                />
                <button
                  onClick={handleRegenerate}
                  disabled={loading || regenCode.length !== 6}
                  className="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-foreground transition-colors hover:bg-gray-50 disabled:opacity-50"
                >
                  Regenerate
                </button>
              </div>

              {regenBackupCodes && (
                <div className="grid grid-cols-2 gap-2 rounded-lg border bg-gray-50 p-3">
                  {regenBackupCodes.map((code, i) => (
                    <p key={i} className="font-mono text-sm font-bold text-gray-700">{code}</p>
                  ))}
                </div>
              )}
            </CardContent>
          </Card>
        )}

        {/* Password Policy Info */}
        <Card>
          <CardHeader>
            <CardTitle>Password Policy</CardTitle>
          </CardHeader>
          <CardContent>
            <ul className="space-y-1 text-sm text-muted-foreground">
              <li>- Minimum 12 characters</li>
              <li>- Must contain uppercase and lowercase letters</li>
              <li>- Must contain at least one number</li>
              <li>- Must contain at least one symbol</li>
              <li>- Password history is checked (last 5 passwords)</li>
            </ul>
          </CardContent>
        </Card>

        {/* Account Lockout Info */}
        <Card>
          <CardHeader>
            <CardTitle>Account Lockout Policy</CardTitle>
          </CardHeader>
          <CardContent>
            <ul className="space-y-1 text-sm text-muted-foreground">
              <li>- After 5 failed attempts: 15 minute lockout</li>
              <li>- After 10 failed attempts: 1 hour lockout</li>
              <li>- After 15 failed attempts: 24 hour lockout</li>
              <li>- Lockouts reset on successful login</li>
            </ul>
          </CardContent>
        </Card>
      </div>
    </DashboardLayout>
  )
}
