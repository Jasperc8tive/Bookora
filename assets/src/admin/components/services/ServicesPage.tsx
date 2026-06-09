/**
 * Services management screen: list, search, filter, paginate, bulk, CRUD.
 */
import { useCallback, useEffect, useState } from 'react';
import { apiDelete, apiGet, apiPost } from '../../api/client';
import type { Paginated, Service, ServiceCategory } from '../../types';
import { ServiceForm } from './ServiceForm';

const EMPTY: Paginated<Service> = { items: [], total: 0, page: 1, per_page: 20, total_pages: 0 };

export function ServicesPage() {
  const [data, setData] = useState<Paginated<Service>>(EMPTY);
  const [categories, setCategories] = useState<ServiceCategory[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');

  const [search, setSearch] = useState('');
  const [status, setStatus] = useState('');
  const [categoryId, setCategoryId] = useState('');
  const [page, setPage] = useState(1);

  const [selected, setSelected] = useState<number[]>([]);
  const [editing, setEditing] = useState<Service | null>(null);
  const [showForm, setShowForm] = useState(false);

  const loadServices = useCallback(async () => {
    setLoading(true);
    setError('');
    try {
      const result = await apiGet<Paginated<Service>>('services', {
        search,
        status,
        category_id: categoryId,
        page,
        per_page: 20,
      });
      setData(result);
    } catch {
      setError('Could not load services.');
    } finally {
      setLoading(false);
    }
  }, [search, status, categoryId, page]);

  const loadCategories = useCallback(async () => {
    try {
      const result = await apiGet<{ items: ServiceCategory[] }>('service-categories');
      setCategories(result.items);
    } catch {
      /* non-fatal */
    }
  }, []);

  useEffect(() => {
    void loadCategories();
  }, [loadCategories]);

  useEffect(() => {
    const handle = setTimeout(() => {
      void loadServices();
    }, 250);
    return () => clearTimeout(handle);
  }, [loadServices]);

  const categoryName = (id: number | null) => categories.find((c) => c.id === id)?.name ?? '—';

  const toggle = (id: number) =>
    setSelected((s) => (s.includes(id) ? s.filter((x) => x !== id) : [...s, id]));

  const toggleAll = () =>
    setSelected((s) => (s.length === data.items.length ? [] : data.items.map((i) => i.id)));

  const runBulk = async (action: string) => {
    if (!action || selected.length === 0) {
      return;
    }
    await apiPost('services/bulk', { action, ids: selected });
    setSelected([]);
    void loadServices();
  };

  const remove = async (id: number) => {
    // eslint-disable-next-line no-alert
    if (!window.confirm('Delete this service?')) {
      return;
    }
    await apiDelete(`services/${id}`);
    void loadServices();
  };

  const addCategory = async () => {
    // eslint-disable-next-line no-alert
    const name = window.prompt('New category name');
    if (!name) {
      return;
    }
    await apiPost('service-categories', { name });
    void loadCategories();
  };

  if (showForm) {
    return (
      <div className="bkra-rounded-lg bkra-border bkra-border-gray-200 bkra-bg-white bkra-p-6">
        <ServiceForm
          service={editing}
          categories={categories}
          onCancel={() => {
            setShowForm(false);
            setEditing(null);
          }}
          onSaved={() => {
            setShowForm(false);
            setEditing(null);
            void loadServices();
          }}
        />
      </div>
    );
  }

  return (
    <section aria-label="Services" className="bkra-space-y-4">
      <div className="bkra-flex bkra-flex-wrap bkra-items-center bkra-gap-2">
        <input
          type="search"
          placeholder="Search services…"
          value={search}
          onChange={(e) => {
            setPage(1);
            setSearch(e.target.value);
          }}
          aria-label="Search services"
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
        <select
          value={categoryId}
          onChange={(e) => {
            setPage(1);
            setCategoryId(e.target.value);
          }}
          aria-label="Filter by category"
          className="bkra-rounded bkra-border bkra-border-gray-300 bkra-px-3 bkra-py-2 bkra-text-sm"
        >
          <option value="">All categories</option>
          {categories.map((c) => (
            <option key={c.id} value={c.id}>
              {c.name}
            </option>
          ))}
        </select>
        <button type="button" onClick={addCategory} className="bkra-rounded bkra-border bkra-border-gray-300 bkra-px-3 bkra-py-2 bkra-text-sm">
          + Category
        </button>
        <div className="bkra-ml-auto">
          <button
            type="button"
            onClick={() => {
              setEditing(null);
              setShowForm(true);
            }}
            className="bkra-rounded bkra-bg-bookora-600 bkra-px-4 bkra-py-2 bkra-text-sm bkra-font-medium bkra-text-white"
          >
            Add service
          </button>
        </div>
      </div>

      {selected.length > 0 && (
        <div className="bkra-flex bkra-items-center bkra-gap-2 bkra-rounded bkra-bg-gray-50 bkra-p-2 bkra-text-sm">
          <span>{selected.length} selected</span>
          <button type="button" onClick={() => runBulk('activate')} className="bkra-rounded bkra-border bkra-px-2 bkra-py-1">
            Activate
          </button>
          <button type="button" onClick={() => runBulk('deactivate')} className="bkra-rounded bkra-border bkra-px-2 bkra-py-1">
            Deactivate
          </button>
          <button type="button" onClick={() => runBulk('delete')} className="bkra-rounded bkra-border bkra-border-red-300 bkra-px-2 bkra-py-1 bkra-text-red-700">
            Delete
          </button>
        </div>
      )}

      {error && <div role="alert" className="bkra-rounded bkra-bg-red-50 bkra-p-3 bkra-text-sm bkra-text-red-700">{error}</div>}

      <table className="bkra-w-full bkra-border-collapse bkra-text-sm">
        <thead>
          <tr className="bkra-border-b bkra-text-left bkra-text-gray-500">
            <th className="bkra-p-2">
              <input
                type="checkbox"
                aria-label="Select all"
                checked={data.items.length > 0 && selected.length === data.items.length}
                onChange={toggleAll}
              />
            </th>
            <th className="bkra-p-2">Name</th>
            <th className="bkra-p-2">Category</th>
            <th className="bkra-p-2">Duration</th>
            <th className="bkra-p-2">Price</th>
            <th className="bkra-p-2">Status</th>
            <th className="bkra-p-2" />
          </tr>
        </thead>
        <tbody>
          {loading && (
            <tr>
              <td colSpan={7} className="bkra-p-4 bkra-text-center bkra-text-gray-500">
                Loading…
              </td>
            </tr>
          )}
          {!loading && data.items.length === 0 && (
            <tr>
              <td colSpan={7} className="bkra-p-4 bkra-text-center bkra-text-gray-500">
                No services found.
              </td>
            </tr>
          )}
          {!loading &&
            data.items.map((service) => (
              <tr key={service.id} className="bkra-border-b">
                <td className="bkra-p-2">
                  <input
                    type="checkbox"
                    aria-label={`Select ${service.name}`}
                    checked={selected.includes(service.id)}
                    onChange={() => toggle(service.id)}
                  />
                </td>
                <td className="bkra-p-2 bkra-font-medium">{service.name}</td>
                <td className="bkra-p-2">{categoryName(service.category_id)}</td>
                <td className="bkra-p-2">{service.duration_min} min</td>
                <td className="bkra-p-2">
                  {service.currency} {service.price}
                </td>
                <td className="bkra-p-2">{service.status}</td>
                <td className="bkra-p-2 bkra-text-right">
                  <button
                    type="button"
                    onClick={() => {
                      setEditing(service);
                      setShowForm(true);
                    }}
                    className="bkra-mr-2 bkra-text-bookora-700"
                  >
                    Edit
                  </button>
                  <button type="button" onClick={() => remove(service.id)} className="bkra-text-red-700">
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
          <button
            type="button"
            disabled={data.page <= 1}
            onClick={() => setPage((p) => Math.max(1, p - 1))}
            className="bkra-rounded bkra-border bkra-px-2 bkra-py-1 disabled:bkra-opacity-40"
          >
            Prev
          </button>
          <span>
            Page {data.page} of {Math.max(1, data.total_pages)}
          </span>
          <button
            type="button"
            disabled={data.page >= data.total_pages}
            onClick={() => setPage((p) => p + 1)}
            className="bkra-rounded bkra-border bkra-px-2 bkra-py-1 disabled:bkra-opacity-40"
          >
            Next
          </button>
        </div>
      </div>
    </section>
  );
}
