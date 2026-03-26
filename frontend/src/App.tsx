import { Routes, Route, Navigate } from 'react-router-dom';
import { Layout } from '@/components/layout/Layout';
import ErrorBoundary from '@/components/common/ErrorBoundary';

// Pages
import { Dashboard } from '@/pages/Dashboard';
import { Festivals } from '@/pages/Festivals';
import { Shows } from '@/pages/Shows';
import { Performers } from '@/pages/Performers';
import { Venues } from '@/pages/Venues';
import { VotingAdmin } from '@/pages/VotingAdmin';
import { Competitions } from '@/pages/Competitions';
import { Volunteers } from '@/pages/Volunteers';
import { Vendors } from '@/pages/Vendors';
import { Sponsors } from '@/pages/Sponsors';
import { FlyerGenerator } from '@/pages/FlyerGenerator';
import { Attendees } from '@/pages/Attendees';
import { Messaging } from '@/pages/Messaging';
import { Analytics } from '@/pages/Analytics';
import { Reports } from '@/pages/Reports';
import { Settings } from '@/pages/Settings';

function App() {
  return (
    <ErrorBoundary>
      <a
        href="#main-content"
        className="sr-only focus:not-sr-only focus:absolute focus:top-2 focus:left-2 focus:z-50 focus:px-4 focus:py-2 focus:bg-white focus:text-blue-600 focus:border focus:border-blue-600 focus:rounded focus:shadow-lg focus:outline-none"
      >
        Skip to main content
      </a>
      <Layout>
        <Routes>
        <Route path="/" element={<Dashboard />} />
        <Route path="/festivals" element={<Festivals />} />
        <Route path="/shows" element={<Shows />} />
        <Route path="/performers" element={<Performers />} />
        <Route path="/venues" element={<Venues />} />
        <Route path="/voting" element={<VotingAdmin />} />
        <Route path="/competitions" element={<Competitions />} />
        <Route path="/volunteers" element={<Volunteers />} />
        <Route path="/vendors" element={<Vendors />} />
        <Route path="/sponsors" element={<Sponsors />} />
        <Route path="/flyers" element={<FlyerGenerator />} />
        <Route path="/attendees" element={<Attendees />} />
        <Route path="/messaging" element={<Messaging />} />
        <Route path="/analytics" element={<Analytics />} />
        <Route path="/reports" element={<Reports />} />
        <Route path="/settings" element={<Settings />} />
        <Route path="*" element={<Navigate to="/" replace />} />
        </Routes>
      </Layout>
    </ErrorBoundary>
  );
}

export default App;
