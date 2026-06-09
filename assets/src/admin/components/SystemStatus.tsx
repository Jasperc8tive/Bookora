/**
 * System Status panel — the Stage 1 end-to-end proof that PHP, REST, React,
 * Vite, and Tailwind are wired together.
 */
import { useEffect, useState } from 'react';
import { apiGet } from '../api/client';
import type { SystemHealth } from '../types';

type LoadState =
  | { status: 'loading' }
  | { status: 'error'; message: string }
  | { status: 'ready'; health: SystemHealth };

function Badge({ ok, children }: { ok: boolean; children: React.ReactNode }) {
  const classes = ok
    ? 'bkra-bg-bookora-50 bkra-text-bookora-700'
    : 'bkra-bg-red-50 bkra-text-red-700';
  return (
    <span className={`bkra-inline-flex bkra-items-center bkra-rounded bkra-px-2 bkra-py-1 bkra-text-xs bkra-font-medium ${classes}`}>
      {children}
    </span>
  );
}

export function SystemStatus() {
  const [state, setState] = useState<LoadState>({ status: 'loading' });

  useEffect(() => {
    let active = true;
    apiGet<SystemHealth>('system/health')
      .then((health) => {
        if (active) {
          setState({ status: 'ready', health });
        }
      })
      .catch((error: unknown) => {
        if (active) {
          const message = error instanceof Error ? error.message : 'Unknown error';
          setState({ status: 'error', message });
        }
      });
    return () => {
      active = false;
    };
  }, []);

  if (state.status === 'loading') {
    return <p className="bkra-text-gray-500">Loading system status…</p>;
  }

  if (state.status === 'error') {
    return (
      <div role="alert" className="bkra-rounded bkra-border bkra-border-red-200 bkra-bg-red-50 bkra-p-4 bkra-text-red-700">
        Could not load system status: {state.message}
      </div>
    );
  }

  const { health } = state;
  const tableEntries = Object.entries(health.database.tables);

  return (
    <section aria-labelledby="bkra-status-heading" className="bkra-space-y-6">
      <header className="bkra-flex bkra-items-center bkra-gap-3">
        <h2 id="bkra-status-heading" className="bkra-text-lg bkra-font-semibold">
          System Status
        </h2>
        <Badge ok={health.healthy}>{health.healthy ? 'Healthy' : 'Attention needed'}</Badge>
      </header>

      <dl className="bkra-grid bkra-grid-cols-2 bkra-gap-4 md:bkra-grid-cols-4">
        <div>
          <dt className="bkra-text-xs bkra-text-gray-500">Plugin version</dt>
          <dd className="bkra-font-medium">{health.plugin.version}</dd>
        </div>
        <div>
          <dt className="bkra-text-xs bkra-text-gray-500">DB schema</dt>
          <dd className="bkra-font-medium">v{health.plugin.db_version}</dd>
        </div>
        <div>
          <dt className="bkra-text-xs bkra-text-gray-500">PHP</dt>
          <dd className="bkra-font-medium">{health.env.php}</dd>
        </div>
        <div>
          <dt className="bkra-text-xs bkra-text-gray-500">WordPress</dt>
          <dd className="bkra-font-medium">{health.env.wp}</dd>
        </div>
      </dl>

      <div>
        <h3 className="bkra-mb-2 bkra-text-sm bkra-font-semibold">Database tables</h3>
        <ul className="bkra-grid bkra-grid-cols-2 bkra-gap-2 md:bkra-grid-cols-3">
          {tableEntries.map(([table, ok]) => (
            <li key={table} className="bkra-flex bkra-items-center bkra-justify-between bkra-rounded bkra-border bkra-border-gray-200 bkra-px-3 bkra-py-2">
              <code className="bkra-text-xs">{table}</code>
              <Badge ok={ok}>{ok ? 'OK' : 'Missing'}</Badge>
            </li>
          ))}
        </ul>
      </div>
    </section>
  );
}
