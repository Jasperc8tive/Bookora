/**
 * Integrations admin: calendar provider connections (Google Calendar, Outlook).
 */
import { useEffect, useState } from 'react';
import { apiGet } from '../../api/client';
import type { Paginated, StaffMember } from '../../types';
import { ProviderIntegration } from './ProviderIntegration';

export function IntegrationsPage() {
  const [staff, setStaff] = useState<StaffMember[]>([]);

  useEffect(() => {
    void apiGet<Paginated<StaffMember>>('staff', { per_page: 100 })
      .then((r) => setStaff(r.items))
      .catch(() => undefined);
  }, []);

  return (
    <div className="bkra-space-y-6">
      <p className="bkra-text-sm bkra-text-gray-600">
        Connect each team member&apos;s calendar so bookings sync both ways and external events block their availability.
      </p>

      <ProviderIntegration
        label="Google Calendar"
        endpoint="integrations/google"
        staff={staff}
        appFields={[
          { key: 'client_id', label: 'OAuth client ID' },
          { key: 'client_secret', label: 'OAuth client secret', secret: true },
        ]}
      />

      <ProviderIntegration
        label="Outlook (Microsoft)"
        endpoint="integrations/microsoft"
        staff={staff}
        appFields={[
          { key: 'client_id', label: 'Application (client) ID' },
          { key: 'client_secret', label: 'Client secret', secret: true },
          { key: 'tenant', label: 'Tenant (default: common)' },
        ]}
      />
    </div>
  );
}
