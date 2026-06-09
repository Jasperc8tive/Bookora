/**
 * Staff management screen: list, search, filter, paginate, CRUD.
 */
import { useCallback, useEffect, useState } from 'react';
import { apiDelete, apiGet } from '../../api/client';
import type { Paginated, Service, StaffMember } from '../../types';
import { StaffForm } from './StaffForm';

const EMPTY: Paginated<StaffMember> = { items: [], total: 0, page: 1, per_page: 20, total_pages: 0 };

export function StaffPage() {
  const [data, setData] = useState<Paginated<StaffMember>>(EMPTY);
  const [services, setServices] = useState<Service[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');

  const [search, setSearch] = useState('');
  const [status, setStatus] = useState('');
  const [page, setPage] = useState(1);

  const [editing, setEditing] = useState<StaffMember | null>(null);
  const [showForm, setShowForm] = useState(false);

  const loadStaff = useCallback(async () => {
    setLoading(true);
    setError('');
    try {
      setData(await apiGet<Paginated<StaffMember>>('staff', { search, status, page, per_page: 20 }));
    } catch {
      setError('Could not load staff.');
    } finally {
      setLoading(false);
    }
  }, [search, status, page]);

  useEffect(() => {
    void apiGet<Paginated<Service>>('services', { per_page: 100 }).then((r) => setServices(r.items)).catch(() => undefined);
  }, []);

  useEffect(() => {
    const handle = setTimeout(() => void loadStaff(), 250);
    return () => clearTimeout(handle);
  }, [loadStaff]);

  const openEdit = async (member: StaffMember) => {
    const full = await apiGet<StaffMember>(`staff/${member.id}`);
    setEditing(full);
    setShowForm(true);
  };

  const remove = async (id: number) => {
    // eslint-disable-next-line no-alert
    if (!window.confirm('Delete this staff member?')) {
      return;
    }
    await apiDelete(`staff/${id}`);
    void loadStaff();
  };

  if (showForm) {
    return (
      <div className="bkra-rounded-lg bkra-border bkra-border-gray-200 bkra-bg-white bkra-p-6">
        <StaffForm
          staff={editing}
          services={services}
          onCancel={() => {
            setShowForm(false);
            setEditing(null);
          }}
          onSaved={() => {
            setShowForm(false);
            setEditing(null);
            void loadStaff();
          }}
        />
      </div>
    );
  }

  return (
    <section aria-label="Staff" className="bkra-space-y-4">
      <div className="bkra-flex bkra-flex-wrap bkra-items-center bkra-gap-2">
        <input
          type="search"
          placeholder="Search staff…"
          value={search}
          onChange={(e) => {
            setPage(1);
            setSearch(e.target.value);
          }}
          aria-label="Search staff"
          className="bkra-rounded bkra-border bkra-border-gray-300 bkra-px-3 bkra-py-2 bkra-text-sm"
        />
        <select
          value={status}
          onChange={(e) => {
            setPage(1);
            setStatus(e.target.value);
          }}
          aria-label="Filter by status"
          className="bkra-rounded bkra-border bkra-border-gray-300 bkra-px-3 bkra-py-2 bkra-text-sm"
        >
          <option value="">All statuses</option>
          <option value="active">Active</option>
          <option value="inactive">Inactive</option>
        </select>
        <div className="bkra-ml-auto">
          <button
            type="button"
            onClick={() => {
              setEditing(null);
              setShowForm(true);
            }}
            className="bkra-rounded bkra-bg-bookora-600 bkra-px-4 bkra-py-2 bkra-text-sm bkra-font-medium bkra-text-white"
          >
            Add staff
          </button>
        </div>
      </div>

      {error && <div role="alert" className="bkra-rounded bkra-bg-red-50 bkra-p-3 bkra-text-sm bkra-text-red-700">{error}</div>}

      <table className="bkra-w-full bkra-border-collapse bkra-text-sm">
        <thead>
          <tr className="bkra-border-b bkra-text-left bkra-text-gray-500">
            <th className="bkra-p-2">Name</th>
            <th className="bkra-p-2">Email</th>
            <th className="bkra-p-2">Phone</th>
            <th className="bkra-p-2">Status</th>
            <th className="bkra-p-2" />
          </tr>
        </thead>
        <tbody>
          {loading && (
            <tr>
              <td colSpan={5} className="bkra-p-4 bkra-text-center bkra-text-gray-500">
                Loading…
              </td>
            </tr>
          )}
          {!loading && data.items.length === 0 && (
            <tr>
              <td colSpan={5} className="bkra-p-4 bkra-text-center bkra-text-gray-500">
                No staff found.
              </td>
            </tr>
          )}
          {!loading &&
            data.items.map((member) => (
              <tr key={member.id} className="bkra-border-b">
                <td className="bkra-p-2 bkra-font-medium">{member.display_name}</td>
                <td className="bkra-p-2">{member.email || '—'}</td>
                <td className="bkra-p-2">{member.phone || '—'}</td>
                <td className="bkra-p-2">{member.status}</td>
                <td className="bkra-p-2 bkra-text-right">
                  <button type="button" onClick={() => void openEdit(member)} className="bkra-mr-2 bkra-text-bookora-700">
                    Edit
                  </button>
                  <button type="button" onClick={() => remove(member.id)} className="bkra-text-red-700">
                    Delete
                  </button>
                </td>
              </tr>
            ))}
        </tbody>
      </table>

      <div className="bkra-flex bkra-items-center bkra-justify-between bkra-text-sm bkra-text-gray-600">
        <span>{data.total} total</span>
        <div className="bkra-flex bkra-items-center bkra-gap-2">
          <button type="button" disabled={data.page <= 1} onClick={() => setPage((p) => Math.max(1, p - 1))} className="bkra-rounded bkra-border bkra-px-2 bkra-py-1 disabled:bkra-opacity-40">
            Prev
          </button>
          <span>
            Page {data.page} of {Math.max(1, data.total_pages)}
          </span>
          <button type="button" disabled={data.page >= data.total_pages} onClick={() => setPage((p) => p + 1)} className="bkra-rounded bkra-border bkra-px-2 bkra-py-1 disabled:bkra-opacity-40">
            Next
          </button>
        </div>
      </div>
    </section>
  );
}
