import { ReactNode, useState } from 'react';
import { NavLink } from 'react-router-dom';
import {
  LayoutDashboard,
  Calendar,
  Theater,
  Users,
  MapPin,
  Vote,
  Trophy,
  Heart,
  Store,
  Megaphone,
  Image,
  Ticket,
  MessageSquare,
  BarChart3,
  FileText,
  Settings,
  ChevronLeft,
} from 'lucide-react';

interface LayoutProps {
  children: ReactNode;
}

const navigation = [
  { name: 'Dashboard', href: '/', icon: LayoutDashboard },
  { name: 'Festivals', href: '/festivals', icon: Calendar },
  { name: 'Shows', href: '/shows', icon: Theater },
  { name: 'Performers', href: '/performers', icon: Users },
  { name: 'Venues', href: '/venues', icon: MapPin },
  { name: 'Voting', href: '/voting', icon: Vote },
  { name: 'Competitions', href: '/competitions', icon: Trophy },
  { name: 'Volunteers', href: '/volunteers', icon: Heart },
  { name: 'Vendors', href: '/vendors', icon: Store },
  { name: 'Sponsors', href: '/sponsors', icon: Megaphone },
  { name: 'Flyers', href: '/flyers', icon: Image },
  { name: 'Attendees', href: '/attendees', icon: Ticket },
  { name: 'Messages', href: '/messaging', icon: MessageSquare },
  { name: 'Analytics', href: '/analytics', icon: BarChart3 },
  { name: 'Reports', href: '/reports', icon: FileText },
  { name: 'Settings', href: '/settings', icon: Settings },
];

export function Layout({ children }: LayoutProps) {
  const [collapsed, setCollapsed] = useState(false);

  return (
    <div className="flex min-h-[100dvh] flex-col bg-gray-50 md:flex-row">
      {/* Sidebar */}
      <aside
        className={`flex-shrink-0 flex flex-col border-b border-gray-200 bg-white transition-all duration-300 md:border-b-0 md:border-r ${
          collapsed ? 'md:w-16' : 'md:w-56'
        }`}
      >
        {/* Logo */}
        <div className="flex items-center justify-between px-3 py-3 md:h-14 md:border-b md:border-gray-200">
          {(!collapsed || typeof window === 'undefined') && (
            <span className="text-lg font-bold text-primary-600">Festival</span>
          )}
          <button
            onClick={() => setCollapsed(!collapsed)}
            className="hidden rounded-lg p-1.5 hover:bg-gray-100 md:inline-flex"
            aria-label={collapsed ? 'Expand navigation' : 'Collapse navigation'}
          >
            <ChevronLeft className={`w-4 h-4 transition-transform ${collapsed ? 'rotate-180' : ''}`} />
          </button>
        </div>

        {/* Navigation */}
        <nav className="flex-1 overflow-x-auto px-2 py-2 md:space-y-0.5 md:overflow-y-auto md:overflow-x-visible md:px-2 md:py-3">
          <div className="flex gap-1 md:flex-col">
          {navigation.map((item) => (
            <NavLink
              key={item.name}
              to={item.href}
              className={({ isActive }) =>
                `flex items-center gap-2 whitespace-nowrap rounded-md px-3 py-2 text-sm font-medium transition-colors ${
                  isActive
                    ? 'bg-primary-50 text-primary-700'
                    : 'text-gray-600 hover:bg-gray-100'
                } ${collapsed ? 'md:justify-center' : ''}`
              }
              title={collapsed ? item.name : undefined}
            >
              <item.icon className="w-4 h-4 flex-shrink-0" />
              {(!collapsed || typeof window === 'undefined') && <span className="md:inline">{item.name}</span>}
            </NavLink>
          ))}
          </div>
        </nav>
      </aside>

      {/* Main content */}
      <main id="main-content" tabIndex={-1} className="flex-1 overflow-auto p-4 sm:p-5 md:p-6">
        {children}
      </main>
    </div>
  );
}
