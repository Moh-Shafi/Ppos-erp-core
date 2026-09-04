import api from '../lib/api';

export interface TwoFactorStatus {
  enabled: boolean;
  enabled_at: string | null;
  backup_codes_remaining: number;
  last_used_at: string | null;
}

export interface TwoFactorEnableResponse {
  qr_code: string;
  secret: string;
  backup_codes: string[];
}

export interface HealthStatus {
  status: 'healthy' | 'degraded';
  timestamp: string;
  checks: Record<string, string>;
}

export interface ConsentInfo {
  consented_at: string;
  consent_type: string;
  privacy_policy_version: string;
  data_types: string[];
}

export const securityService = {
  async get2faStatus(): Promise<TwoFactorStatus> {
    const res = await api.get('/auth/2fa/status');
    return res.data;
  },

  async enable2fa(): Promise<TwoFactorEnableResponse> {
    const res = await api.post('/auth/2fa/enable');
    return res.data;
  },

  async verify2fa(code: string): Promise<{ verified: boolean }> {
    const res = await api.post('/auth/2fa/verify', { code });
    return res.data;
  },

  async disable2fa(code: string): Promise<{ disabled: boolean }> {
    const res = await api.post('/auth/2fa/disable', { code });
    return res.data;
  },

  async regenerateBackupCodes(code: string): Promise<{ backup_codes: string[] }> {
    const res = await api.post('/auth/2fa/backup-codes', { code });
    return res.data;
  },

  async loginWith2fa(token: string, code: string, isBackup = false): Promise<any> {
    const res = await api.post('/auth/login-2fa', {
      '2fa_token': token,
      code,
      is_backup: isBackup,
    });
    return res.data;
  },

  async unlockUser(userId: number): Promise<{ unlocked: boolean }> {
    const res = await api.post(`/admin/users/${userId}/unlock`);
    return res.data;
  },

  async reset2fa(userId: number): Promise<{ reset: boolean }> {
    const res = await api.post(`/admin/users/${userId}/reset-2fa`);
    return res.data;
  },

  async getHealth(): Promise<HealthStatus> {
    const res = await api.get('/health');
    return res.data;
  },

  async exportAccountData(): Promise<any> {
    const res = await api.get('/account/export');
    return res.data;
  },

  async deleteAccount(password: string): Promise<any> {
    const res = await api.delete('/account', { data: { password } });
    return res.data;
  },

  async getConsent(): Promise<ConsentInfo> {
    const res = await api.get('/account/consent');
    return res.data;
  },

  async exportAuditLogsCsv(): Promise<Blob> {
    const res = await api.get('/audit-logs/export', { responseType: 'blob' });
    return res.data;
  },
};
