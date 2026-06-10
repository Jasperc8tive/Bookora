/**
 * Customer CRM screen: list, search, tag filter, paginate, CRUD.
 */
import { useCallback, useEffect, useState } from 'react';
import { apiDelete, apiGet } from '../../api/client';
import type { Customer, Paginated } from '../../types';
import { CustomerForm } from './CustomerForm';

const EMPTY: Paginated<Customer> = { items: [], total: 0, page: 1, per_page: 20, total_pages: 0 };

export function CustomersPage() {
  const [data, setData] = useState<Paginated<Customer>>(EMPTY);
  const [tags, setTags] = useState<string[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');

  const [search, setSearch] = useState('');
  const [tag, setTag] = useState('');
  const [page, setPage] = useState(1);

  const [editing, setEditing] = useState<Customer | null>(null);
  const [showForm, setShowForm] = useState(false);

  const load = useCallback(async () => {
    setLoading(true);
    setError('');
    try {
      setData(await apiGet<Paginated<Customer>>('customers', { search, tag, page, per_page: 20 }));
    } catch {
      setError('Could not load customers.');
    } finally {
      setLoading(false);
    }
  }, [search, tag, page]);

  useEffect(() => {
    void apiGet<{ tags: string[] }>('customers/tags').then((r) => setTags(r.tags)).catch(() => undefined);
  }, [showForm]);

  useEffect(() => {
    const handle = setTimeout(() => void load(), 250);
    return () => clearTimeout(handle);
  }, [load]);

  const openEdit = async (customer: Customer) => {
    setEditing(await apiGet<Customer>(`customers/${customer.id}`));
    setShowForm(true);
  };

  const remove = async (id: number) => {
    // eslint-disable-next-line no-alert
    if (!window.confirm('Delete this customer?')) {
      return;
    }
    await apiDelete(`customers/${id}`);
    void load();
  };

  if (showForm) {
    return (
      <div className="bkra-rounded-lg bkra-border bkra-border-gray-200 bkra-bg-white bkra-p-6">
        <CustomerForm
          customer={editing}
          onCancel={() => {
            setShowForm(false);
            setEditing(null);
          }}
          onSaved={() => {
            setShowForm(false);
            setEditing(null);
            void load();
          }}
        />
      </div>
    );
  }

  return (
    <section aria-label="Customers" className="bkra-space-y-4">
      <div className="bkra-flex bkra-flex-wrap bkra-items-center bkra-gap-2">
        <input
          type="search"
          placeholder="Search customers…"
          value={search}
          onChange={(e) => {
            setPage(1);
            setSearch(e.target.value);
          }}
          aria-label="Search customers"
          className="bkra-rounded bkra-border bkra-border-gray-300 bkra-px-3 bkra-py-2 bkra-text-sm"
        />
        <select
          value={tag}
          onChange={(e) => {
            setPage(1);
            setTag(e.target.value);
          }}
          aria-label="Filter by tag"
          className="bkra-rounded bkra-border bkra-border-gray-300 bkra-px-3 bkra-py-2 bkra-text-sm"
        >
          <option value="">All tags</option>
          {tags.map((t) => (
            <option key={t} value={t}>
              {t}
            </option>
          ))}
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
            Add customer
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
            <th className="bkra-p-2">Tags</th>
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
                No customers found.
              </td>
            </tr>
          )}
          {!loading &&
            data.items.map((customer) => (
              <tr key={customer.id} className="bkra-border-b">
                <td className="bkra-p-2 bkra-font-medium">{customer.name || '—'}</td>
                <td className="bkra-p-2">{customer.email || '—'}</td>
                <td className="bkra-p-2">{customer.phone || '—'}</td>
                <td className="bkra-p-2">{customer.tags || '—'}</td>
                <td className="bkra-p-2 bkra-text-right">
                  <button type="button" onClick={() => void openEdit(customer)} className="bkra-mr-2 bkra-text-bookora-700">
                    Open
                  </button>
                  <button type="button" onClick={() => remove(customer.id)} className="bkra-text-red-700">
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
