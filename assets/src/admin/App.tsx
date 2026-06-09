/**
 * Root admin component: hosts navigation and the active screen.
 */
import { useState } from 'react';
import { Nav, type Screen } from './components/Nav';
import { SystemStatus } from './components/SystemStatus';
import { ServicesPage } from './components/services/ServicesPage';

function initialScreen(): Screen {
  return window.BookoraAdmin?.screen === 'services' ? 'services' : 'dashboard';
}

export function App() {
  const version = window.BookoraAdmin?.version ?? '';
  const [screen, setScreen] = useState<Screen>(initialScreen);

  return (
    <div className="bkra-mx-auto bkra-max-w-5xl bkra-py-6">
      <header className="bkra-mb-6">
        <h1 className="bkra-text-2xl bkra-font-bold bkra-text-bookora-700">Bookora</h1>
        <p className="bkra-text-sm bkra-text-gray-500">
          Appointment booking platform{version ? ` · v${version}` : ''}
        </p>
      </header>

      <Nav active={screen} onChange={setScreen} />

      <div className="bkra-rounded-lg bkra-border bkra-border-gray-200 bkra-bg-white bkra-p-6 bkra-shadow-sm">
        {screen === 'dashboard' ? <SystemStatus /> : <ServicesPage />}
      </div>
    </div>
  );
}
