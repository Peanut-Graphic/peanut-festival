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
    <div className="flex bg-gray-50" style={{ minHeight: 'calc(100vh - 32px)' }}>
      {/* Sidebar */}
      <aside
        className={`flex-shrink-0 flex flex-col bg-white border-r border-gray-200 transition-all duration-300 ${
          collapsed ? 'w-16' : 'w-56'
        }`}
      >
        {/* Logo */}
        <div className="flex items-center justify-between h-14 px-3 border-b border-gray-200">
          {!collapsed && (
            <span className="text-lg font-bold text-primary-600">Festival</span>
          )}
          <button
            onClick={() => setCollapsed(!collapsed)}
            className="p-1.5 rounded-lg hover:bg-gray-100"
          >
            <ChevronLeft className={`w-4 h-4 transition-transform ${collapsed ? 'rotate-180' : ''}`} />
          </button>
        </div>

        {/* Navigation */}
        <nav className="flex-1 px-2 py-3 space-y-0.5 overflow-y-auto">
          {navigation.map((item) => (
            <NavLink
              key={item.name}
              to={item.href}
              className={({ isActive }) =>
                `flex items-center gap-2 px-2 py-1.5 rounded-md text-sm font-medium transition-colors ${
                  isActive
                    ? 'bg-primary-50 text-primary-700'
                    : 'text-gray-600 hover:bg-gray-100'
                } ${collapsed ? 'justify-center' : ''}`
              }
              title={collapsed ? item.name : undefined}
            >
              <item.icon className="w-4 h-4 flex-shrink-0" />
              {!collapsed && <span>{item.name}</span>}
            </NavLink>
          ))}
        </nav>
      </aside>

      {/* Main content */}
      <main id="main-content" tabIndex={-1} className="flex-1 p-4 overflow-auto">
        {children}
      </main>
    </div>
  );
}
