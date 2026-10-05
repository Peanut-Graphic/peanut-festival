import { useQuery } from '@tanstack/react-query';
import { dashboardApi } from '@/api/endpoints';
import { Users, Theater, DollarSign, Heart } from 'lucide-react';

export function Dashboard() {
  const { data: stats, isLoading } = useQuery({
    queryKey: ['dashboard-stats'],
    queryFn: dashboardApi.getStats,
  });

  if (isLoading) {
    return (
      <div className="animate-pulse space-y-6">
        <div className="h-8 bg-gray-200 rounded-sm w-48" />
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
          {[...Array(4)].map((_, i) => (
            <div key={i} className="h-32 bg-gray-200 rounded-xl" />
          ))}
        </div>
      </div>
    );
  }

  const statCards = [
    {
      label: 'Total Shows',
      value: stats?.shows.total ?? 0,
      subtext: `${stats?.shows.scheduled ?? 0} scheduled`,
      icon: Theater,
      color: 'bg-blue-500',
    },
    {
      label: 'Performers',
      value: Object.values(stats?.performers ?? {}).reduce((a, b) => a + b, 0),
      subtext: `${stats?.performers.accepted ?? 0} accepted`,
      icon: Users,
      color: 'bg-purple-500',
    },
    {
      label: 'Volunteers',
      value: stats?.volunteers.total_volunteers ?? 0,
      subtext: `${stats?.volunteers.total_hours ?? 0} hours logged`,
      icon: Heart,
      color: 'bg-pink-500',
    },
    {
      label: 'Revenue',
      value: `$${(stats?.financials.total_income ?? 0).toLocaleString()}`,
      subtext: `Net: $${(stats?.financials.net ?? 0).toLocaleString()}`,
      icon: DollarSign,
      color: 'bg-green-500',
    },
  ];

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-bold text-gray-900">Dashboard</h1>
      </div>

      {/* Stats Grid */}
      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        {statCards.map((stat) => (
          <div key={stat.label} className="pf-card p-6">
            <div className="flex items-center gap-4">
              <div className={`p-3 rounded-lg ${stat.color}`}>
                <stat.icon className="w-6 h-6 text-white" />
              </div>
              <div>
                <p className="text-sm text-gray-500">{stat.label}</p>
                <p className="text-2xl font-bold text-gray-900">{stat.value}</p>
                <p className="text-xs text-gray-400">{stat.subtext}</p>
              </div>
            </div>
          </div>
        ))}
      </div>

      {/* Additional sections */}
      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {/* Upcoming Shows */}
        <div className="pf-card p-6">
          <h2 className="text-lg font-semibold mb-4">Upcoming Shows</h2>
          <div className="space-y-3">
            {stats?.shows.scheduled === 0 ? (
              <p className="text-gray-500 text-sm">No upcoming shows</p>
            ) : (
              <p className="text-gray-500 text-sm">
                {stats?.shows.scheduled} shows scheduled
              </p>
            )}
          </div>
        </div>

        {/* Ticket Sales */}
        <div className="pf-card p-6">
          <h2 className="text-lg font-semibold mb-4">Ticket Sales</h2>
          <div className="space-y-3">
            <div className="flex justify-between items-center">
              <span className="text-gray-500">Total Tickets</span>
              <span className="font-semibold">{stats?.tickets.total_tickets ?? 0}</span>
            </div>
            <div className="flex justify-between items-center">
              <span className="text-gray-500">Checked In</span>
              <span className="font-semibold">{stats?.tickets.checked_in ?? 0}</span>
            </div>
            <div className="flex justify-between items-center">
              <span className="text-gray-500">Revenue</span>
              <span className="font-semibold text-green-600">
                ${(stats?.tickets.total_revenue ?? 0).toLocaleString()}
              </span>
            </div>
          </div>
        </div>

        {/* Performer Applications */}
        <div className="pf-card p-6">
          <h2 className="text-lg font-semibold mb-4">Performer Applications</h2>
          <div className="space-y-2">
            <div className="flex justify-between items-center">
              <span className="text-gray-500">Pending</span>
              <span className="badge badge-yellow">{stats?.performers.pending ?? 0}</span>
            </div>
            <div className="flex justify-between items-center">
              <span className="text-gray-500">Under Review</span>
              <span className="badge badge-blue">{stats?.performers.under_review ?? 0}</span>
            </div>
            <div className="flex justify-between items-center">
              <span className="text-gray-500">Accepted</span>
              <span className="badge badge-green">{stats?.performers.accepted ?? 0}</span>
            </div>
          </div>
        </div>

        {/* Volunteer Shifts */}
        <div className="pf-card p-6">
          <h2 className="text-lg font-semibold mb-4">Volunteer Shifts</h2>
          <div className="space-y-2">
            <div className="flex justify-between items-center">
              <span className="text-gray-500">Total Shifts</span>
              <span className="font-semibold">{stats?.volunteers.total_shifts ?? 0}</span>
            </div>
            <div className="flex justify-between items-center">
              <span className="text-gray-500">Slots Available</span>
              <span className="font-semibold">
                {(stats?.volunteers.total_slots ?? 0) - (stats?.volunteers.filled_slots ?? 0)}
              </span>
            </div>
            <div className="flex justify-between items-center">
              <span className="text-gray-500">Fill Rate</span>
              <span className="font-semibold">
                {stats?.volunteers.total_slots
                  ? Math.round((stats.volunteers.filled_slots / stats.volunteers.total_slots) * 100)
                  : 0}%
              </span>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}
