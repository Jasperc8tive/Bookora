/**
 * Create / edit form for a service.
 */
import { useState } from 'react';
import { apiPatch, apiPost, ApiError } from '../../api/client';
import type { Service, ServiceCategory } from '../../types';

interface Props {
  service: Service | null;
  categories: ServiceCategory[];
  onSaved: (service: Service) => void;
  onCancel: () => void;
}

type FormState = Record<string, string>;

function initialState(service: Service | null): FormState {
  return {
    name: service?.name ?? '',
    category_id: service?.category_id ? String(service.category_id) : '',
    status: service?.status ?? 'active',
    duration_min: String(service?.duration_min ?? 30),
    buffer_before_min: String(service?.buffer_before_min ?? 0),
    buffer_after_min: String(service?.buffer_after_min ?? 0),
    price: service?.price ?? '0.00',
    deposit_type: service?.deposit_type ?? 'none',
    deposit_value: service?.deposit_value ?? '0.00',
    capacity: String(service?.capacity ?? 1),
    image_url: service?.image_url ?? '',
    description: service?.description ?? '',
  };
}

export function ServiceForm({ service, categories, onSaved, onCancel }: Props) {
  const [values, setValues] = useState<FormState>(() => initialState(service));
  const [errors, setErrors] = useState<Record<string, string>>({});
  const [saving, setSaving] = useState(false);
  const [formError, setFormError] = useState('');

  const set = (key: string, value: string) => setValues((v) => ({ ...v, [key]: value }));

  const pickImage = () => {
    const media = window.wp?.media;
    if (!media) {
      return;
    }
    const frame = media({ title: 'Select service image', multiple: false });
    frame.on('select', () => {
      const attachment = frame.state().get('selection').first().toJSON();
      set('image_url', attachment.url);
    });
    frame.open();
  };

  const submit = async (event: React.FormEvent) => {
    event.preventDefault();
    setSaving(true);
    setErrors({});
    setFormError('');

    const payload = {
      ...values,
      category_id: values.category_id ? Number(values.category_id) : 0,
      duration_min: Number(values.duration_min),
      buffer_before_min: Number(values.buffer_before_min),
      buffer_after_min: Number(values.buffer_after_min),
      price: Number(values.price),
      deposit_value: Number(values.deposit_value),
      capacity: Number(values.capacity),
    };

    try {
      const saved = service
        ? await apiPatch<Service>(`services/${service.id}`, payload)
        : await apiPost<Service>('services', payload);
      onSaved(saved);
    } catch (error) {
      if (error instanceof ApiError) {
        setErrors(error.fields);
        setFormError(error.message);
      } else {
        setFormError('Something went wrong.');
      }
    } finally {
      setSaving(false);
    }
  };

  const field = (label: string, key: string, type = 'text') => (
    <label className="bkra-block">
      <span className="bkra-mb-1 bkra-block bkra-text-xs bkra-font-medium bkra-text-gray-600">{label}</span>
      <input
        type={type}
        value={values[key]}
        onChange={(e) => set(key, e.target.value)}
        className="bkra-w-full bkra-rounded bkra-border bkra-border-gray-300 bkra-px-3 bkra-py-2 bkra-text-sm"
      />
      {errors[key] && <span className="bkra-mt-1 bkra-block bkra-text-xs bkra-text-red-600">{errors[key]}</span>}
    </label>
  );

  return (
    <form onSubmit={submit} className="bkra-space-y-4" aria-label={service ? 'Edit service' : 'Add service'}>
      <h2 className="bkra-text-lg bkra-font-semibold">{service ? 'Edit service' : 'Add service'}</h2>

      {formError && (
        <div role="alert" className="bkra-rounded bkra-bg-red-50 bkra-p-3 bkra-text-sm bkra-text-red-700">
          {formError}
        </div>
      )}

      {field('Name', 'name')}

      <div className="bkra-grid bkra-grid-cols-2 bkra-gap-4">
        <label className="bkra-block">
          <span className="bkra-mb-1 bkra-block bkra-text-xs bkra-font-medium bkra-text-gray-600">Category</span>
          <select
            value={values.category_id}
            onChange={(e) => set('category_id', e.target.value)}
            className="bkra-w-full bkra-rounded bkra-border bkra-border-gray-300 bkra-px-3 bkra-py-2 bkra-text-sm"
          >
            <option value="">— None —</option>
            {categories.map((c) => (
              <option key={c.id} value={c.id}>
                {c.name}
              </option>
            ))}
          </select>
        </label>
        <label className="bkra-block">
          <span className="bkra-mb-1 bkra-block bkra-text-xs bkra-font-medium bkra-text-gray-600">Status</span>
          <select
            value={values.status}
            onChange={(e) => set('status', e.target.value)}
            className="bkra-w-full bkra-rounded bkra-border bkra-border-gray-300 bkra-px-3 bkra-py-2 bkra-text-sm"
          >
            <option value="active">Active</option>
            <option value="inactive">Inactive</option>
          </select>
        </label>
      </div>

      <div className="bkra-grid bkra-grid-cols-3 bkra-gap-4">
        {field('Duration (min)', 'duration_min', 'number')}
        {field('Buffer before', 'buffer_before_min', 'number')}
        {field('Buffer after', 'buffer_after_min', 'number')}
      </div>

      <div className="bkra-grid bkra-grid-cols-3 bkra-gap-4">
        {field('Price', 'price', 'number')}
        <label className="bkra-block">
          <span className="bkra-mb-1 bkra-block bkra-text-xs bkra-font-medium bkra-text-gray-600">Deposit type</span>
          <select
            value={values.deposit_type}
            onChange={(e) => set('deposit_type', e.target.value)}
            className="bkra-w-full bkra-rounded bkra-border bkra-border-gray-300 bkra-px-3 bkra-py-2 bkra-text-sm"
          >
            <option value="none">None</option>
            <option value="fixed">Fixed</option>
            <option value="percent">Percent</option>
          </select>
        </label>
        {field('Deposit value', 'deposit_value', 'number')}
      </div>

      <div className="bkra-grid bkra-grid-cols-2 bkra-gap-4">
        {field('Capacity', 'capacity', 'number')}
        <label className="bkra-block">
          <span className="bkra-mb-1 bkra-block bkra-text-xs bkra-font-medium bkra-text-gray-600">Image</span>
          <div className="bkra-flex bkra-gap-2">
            <input
              type="text"
              value={values.image_url}
              onChange={(e) => set('image_url', e.target.value)}
              className="bkra-w-full bkra-rounded bkra-border bkra-border-gray-300 bkra-px-3 bkra-py-2 bkra-text-sm"
            />
            <button
              type="button"
              onClick={pickImage}
              className="bkra-whitespace-nowrap bkra-rounded bkra-border bkra-border-gray-300 bkra-px-3 bkra-text-sm"
            >
              Select
            </button>
          </div>
        </label>
      </div>

      <label className="bkra-block">
        <span className="bkra-mb-1 bkra-block bkra-text-xs bkra-font-medium bkra-text-gray-600">Description</span>
        <textarea
          value={values.description}
          onChange={(e) => set('description', e.target.value)}
          rows={3}
          className="bkra-w-full bkra-rounded bkra-border bkra-border-gray-300 bkra-px-3 bkra-py-2 bkra-text-sm"
        />
      </label>

      <div className="bkra-flex bkra-justify-end bkra-gap-2">
        <button type="button" onClick={onCancel} className="bkra-rounded bkra-border bkra-border-gray-300 bkra-px-4 bkra-py-2 bkra-text-sm">
          Cancel
        </button>
        <button
          type="submit"
          disabled={saving}
          className="bkra-rounded bkra-bg-bookora-600 bkra-px-4 bkra-py-2 bkra-text-sm bkra-font-medium bkra-text-white disabled:bkra-opacity-50"
        >
          {saving ? 'Saving…' : 'Save service'}
        </button>
      </div>
    </form>
  );
}
