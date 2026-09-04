import { useState, useEffect, useCallback } from 'react'
import { DashboardLayout } from '@/layouts/DashboardLayout'
import { Card, CardHeader, CardTitle, CardContent } from '@/components/ui/Card'
import { securityService, type ConsentInfo } from '@/services/security'

export function PrivacySettingsPage() {
  const [consent, setConsent] = useState<ConsentInfo | null>(null)
  const [exportLoading, setExportLoading] = useState(false)
  const [deleteLoading, setDeleteLoading] = useState(false)
  const [deletePassword, setDeletePassword] = useState('')
  const [error, setError] = useState('')
  const [success, setSuccess] = useState('')
  const [showDeleteConfirm, setShowDeleteConfirm] = useState(false)

  const fetchConsent = useCallback(async () => {
    try {
      const c = await securityService.getConsent()
      setConsent(c)
    } catch {
      // ignore
    }
  }, [])

  useEffect(() => {
    fetchConsent()
  }, [fetchConsent])

  const handleExport = async () => {
    setExportLoading(true)
    setError('')
    setSuccess('')
    try {
      const data = await securityService.exportAccountData()
      const blob = new Blob([JSON.stringify(data, null, 2)], { type: 'application/json' })
      const url = window.URL.createObjectURL(blob)
      const a = document.createElement('a')
      a.href = url
      a.download = `account-export-${new Date().toISOString().split('T')[0]}.json`
      a.click()
      window.URL.revokeObjectURL(url)
      setSuccess('Data exported successfully!')
    } catch {
      setError('Failed to export data')
    } finally {
      setExportLoading(false)
    }
  }

  const handleDelete = async () => {
    setDeleteLoading(true)
    setError('')
    setSuccess('')
    try {
      await securityService.deleteAccount(deletePassword)
      setSuccess('Account deletion scheduled. You will be logged out.')
      setTimeout(() => {
        window.location.href = '/login'
      }, 2000)
    } catch {
      setError('Failed to delete account. Please check your password.')
    } finally {
      setDeleteLoading(false)
    }
  }

  return (
    <DashboardLayout>
      <div className="max-w-2xl space-y-6">
        <div>
          <h1 className="text-2xl font-bold text-foreground">Privasi Data</h1>
          <p className="text-muted-foreground">Manage your data privacy and compliance with PDP Law</p>
        </div>

        {error && (
          <div className="rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-700">{error}</div>
        )}
        {success && (
          <div className="rounded-lg border border-green-200 bg-green-50 p-3 text-sm text-green-700">{success}</div>
        )}

        {/* Consent Info */}
        <Card>
          <CardHeader>
            <CardTitle>Data Consent</CardTitle>
          </CardHeader>
          <CardContent className="space-y-3">
            {consent && (
              <>
                <div>
                  <p className="text-sm font-medium text-muted-foreground">Consented At</p>
                  <p className="text-foreground">{new Date(consent.consented_at).toLocaleString()}</p>
                </div>
                <div>
                  <p className="text-sm font-medium text-muted-foreground">Consent Type</p>
                  <p className="text-foreground">{consent.consent_type}</p>
                </div>
                <div>
                  <p className="text-sm font-medium text-muted-foreground">Privacy Policy Version</p>
                  <p className="text-foreground">{consent.privacy_policy_version}</p>
                </div>
                <div>
                  <p className="text-sm font-medium text-muted-foreground">Data Types Collected</p>
                  <div className="mt-1 flex flex-wrap gap-2">
                    {consent.data_types.map((type) => (
                      <span key={type} className="rounded-md bg-blue-50 px-2 py-0.5 text-xs font-medium text-blue-700">
                        {type}
                      </span>
                    ))}
                  </div>
                </div>
              </>
            )}
          </CardContent>
        </Card>

        {/* Data Export */}
        <Card>
          <CardHeader>
            <CardTitle>Export Your Data</CardTitle>
          </CardHeader>
          <CardContent className="space-y-3">
            <p className="text-sm text-muted-foreground">
              Download a copy of all your personal data including profile, sales, payments, and audit logs.
              This is your right under the PDP Law (UU PDP).
            </p>
            <button
              onClick={handleExport}
              disabled={exportLoading}
              className="rounded-lg bg-[#f54927] px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-[#e03e1e] disabled:opacity-50"
            >
              {exportLoading ? 'Exporting...' : 'Export My Data'}
            </button>
          </CardContent>
        </Card>

        {/* Account Deletion */}
        <Card>
          <CardHeader>
            <CardTitle className="text-red-600">Delete Account</CardTitle>
          </CardHeader>
          <CardContent className="space-y-3">
            <p className="text-sm text-muted-foreground">
              Permanently delete your account and all associated data. This action will:
            </p>
            <ul className="space-y-1 text-sm text-muted-foreground">
              <li>- Anonymize your personal information</li>
              <li>- Soft-delete your account (30-day grace period)</li>
              <li>- Log the deletion request in audit logs</li>
              <li>- Revoke all active sessions and tokens</li>
            </ul>

            {!showDeleteConfirm ? (
              <button
                onClick={() => setShowDeleteConfirm(true)}
                className="rounded-lg border border-red-300 px-4 py-2 text-sm font-medium text-red-600 transition-colors hover:bg-red-50"
              >
                Request Account Deletion
              </button>
            ) : (
              <div className="space-y-3 rounded-lg border border-red-200 bg-red-50 p-4">
                <p className="text-sm font-medium text-red-700">Confirm Deletion</p>
                <p className="text-xs text-red-600">Enter your password to confirm. Your account will be scheduled for deletion.</p>
                <input
                  type="password"
                  value={deletePassword}
                  onChange={(e) => setDeletePassword(e.target.value)}
                  placeholder="Your password"
                  className="w-full rounded-lg border border-red-300 px-3 py-2 text-sm"
                />
                <div className="flex gap-2">
                  <button
                    onClick={handleDelete}
                    disabled={deleteLoading || !deletePassword}
                    className="rounded-lg bg-red-600 px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-red-700 disabled:opacity-50"
                  >
                    {deleteLoading ? 'Deleting...' : 'Confirm Delete'}
                  </button>
                  <button
                    onClick={() => {
                      setShowDeleteConfirm(false)
                      setDeletePassword('')
                    }}
                    className="rounded-lg border px-4 py-2 text-sm font-medium transition-colors hover:bg-gray-50"
                  >
                    Cancel
                  </button>
                </div>
              </div>
            )}
          </CardContent>
        </Card>

        {/* PDP Compliance Info */}
        <Card>
          <CardHeader>
            <CardTitle>PDP Compliance (UU PDP)</CardTitle>
          </CardHeader>
          <CardContent>
            <ul className="space-y-2 text-sm text-muted-foreground">
              <li>- Your data is processed in accordance with Indonesia's Personal Data Protection Law (UU PDP)</li>
              <li>- You have the right to access, export, and delete your personal data</li>
              <li>- Sensitive fields in audit logs are automatically redacted</li>
              <li>- Data retention period: 90 days for audit logs</li>
              <li>- Account deletion has a 30-day grace period before permanent purge</li>
            </ul>
          </CardContent>
        </Card>
      </div>
    </DashboardLayout>
  )
}
