/**
 * Top-level admin navigation between Bookora screens.
 */
export type Screen = 'dashboard' | 'services' | 'staff' | 'customers' | 'calendar';

const TABS: { id: Screen; label: string }[] = [
  { id: 'dashboard', label: 'Dashboard' },
  { id: 'calendar', label: 'Calendar' },
  { id: 'services', label: 'Services' },
  { id: 'staff', label: 'Staff' },
  { id: 'customers', label: 'Customers' },
];

export function Nav({ active, onChange }: { active: Screen; onChange: (s: Screen) => void }) {
  return (
    <nav className="bkra-mb-6 bkra-flex bkra-gap-1 bkra-border-b bkra-border-gray-200">
      {TABS.map((tab) => {
        const isActive = tab.id === active;
        return (
          <button
            key={tab.id}
            type="button"
            onClick={() => onChange(tab.id)}
            aria-current={isActive ? 'page' : undefined}
            className={
              'bkra-border-b-2 bkra-px-4 bkra-py-2 bkra-text-sm bkra-font-medium ' +
              (isActive
                ? 'bkra-border-bookora-600 bkra-text-bookora-700'
                : 'bkra-border-transparent bkra-text-gray-500 hover:bkra-text-gray-700')
            }
          >
            {tab.label}
          </button>
        );
      })}
    </nav>
  );
}
