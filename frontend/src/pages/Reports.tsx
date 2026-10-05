import { useState } from 'react';
import { useQuery, useMutation } from '@tanstack/react-query';
import { reportsApi } from '@/api/endpoints';
import { BarChart3, TrendingUp, Clock, FileSpreadsheet, Users, Ticket, DollarSign } from 'lucide-react';

// Simple Bar Chart Component
function BarChart({
  data,
  labelKey,
  valueKey,
  height = 200,
  color = '#7c3aed'
}: {
  data: Array<Record<string, unknown>>;
  labelKey: string;
  valueKey: string;
  height?: number;
  color?: string;
}) {
  if (!data || data.length === 0) {
    return <div className="text-gray-400 text-center py-8">No data available</div>;
  }

  const maxValue = Math.max(...data.map(d => Number(d[valueKey]) || 0));
  const barWidth = Math.max(20, Math.min(60, Math.floor(600 / data.length) - 4));

  return (
    <div className="overflow-x-auto">
      <svg width={Math.max(600, data.length * (barWidth + 4))} height={height + 40} className="block">
        {data.map((item, index) => {
          const value = Number(item[valueKey]) || 0;
          const barHeight = maxValue > 0 ? (value / maxValue) * height : 0;
          const x = index * (barWidth + 4) + 2;
          const y = height - barHeight;

          return (
            <g key={index}>
              <rect
                x={x}
                y={y}
                width={barWidth}
                height={barHeight}
                fill={color}
                rx={2}
                className="hover:opacity-80 transition-opacity"
              />
              <title>{`${item[labelKey]}: ${value.toLocaleString()}`}</title>
              <text
                x={x + barWidth / 2}
                y={height + 16}
                textAnchor="middle"
                className="text-xs fill-gray-500"
                fontSize={10}
              >
                {String(item[labelKey]).slice(-5)}
              </text>
            </g>
          );
        })}
      </svg>
    </div>
  );
}

// Line Chart Component for Revenue
function LineChart({
  data,
  height = 200
}: {
  data: Array<{ date: string; income: number; expense: number }>;
  height?: number;
}) {
  if (!data || data.length === 0) {
    return <div className="text-gray-400 text-center py-8">No data available</div>;
  }

  const width = Math.max(600, data.length * 30);
  const padding = { top: 10, right: 20, bottom: 30, left: 50 };
  const chartWidth = width - padding.left - padding.right;
  const chartHeight = height - padding.top - padding.bottom;

  const maxValue = Math.max(
    ...data.map(d => Math.max(d.income, d.expense))
  );

  const getX = (index: number) => padding.left + (index / (data.length - 1)) * chartWidth;
  const getY = (value: number) => padding.top + chartHeight - (value / maxValue) * chartHeight;

  const incomePath = data.map((d, i) => `${i === 0 ? 'M' : 'L'} ${getX(i)} ${getY(d.income)}`).join(' ');
  const expensePath = data.map((d, i) => `${i === 0 ? 'M' : 'L'} ${getX(i)} ${getY(d.expense)}`).join(' ');

  return (
    <div className="overflow-x-auto">
      <svg width={width} height={height} className="block">
        <path d={incomePath} fill="none" stroke="#10b981" strokeWidth={2} />
        <path d={expensePath} fill="none" stroke="#ef4444" strokeWidth={2} />

        {data.map((d, i) => (
          <g key={i}>
            <circle cx={getX(i)} cy={getY(d.income)} r={3} fill="#10b981" />
            <circle cx={getX(i)} cy={getY(d.expense)} r={3} fill="#ef4444" />
            <text
              x={getX(i)}
              y={height - 5}
              textAnchor="middle"
              className="text-xs fill-gray-500"
              fontSize={10}
            >
              {d.date.slice(-5)}
            </text>
          </g>
        ))}

        {/* Legend */}
        <g transform={`translate(${padding.left}, ${height - 25})`}>
          <rect x={0} y={0} width={10} height={10} fill="#10b981" rx={2} />
          <text x={15} y={9} fontSize={10} className="fill-gray-600">Income</text>
          <rect x={70} y={0} width={10} height={10} fill="#ef4444" rx={2} />
          <text x={85} y={9} fontSize={10} className="fill-gray-600">Expense</text>
        </g>
      </svg>
    </div>
  );
}

export function Reports() {
  const [period, setPeriod] = useState<'daily' | 'weekly' | 'monthly'>('daily');
  const [activeTab, setActiveTab] = useState<'tickets' | 'revenue' | 'activity'>('tickets');

  const { data: ticketSales, isLoading: ticketsLoading } = useQuery({
    queryKey: ['ticket-sales', period],
    queryFn: () => reportsApi.getTicketSales(undefined, period),
  });

  const { data: revenue, isLoading: revenueLoading } = useQuery({
    queryKey: ['revenue-report'],
    queryFn: () => reportsApi.getRevenue(),
  });

  const { data: activity, isLoading: activityLoading } = useQuery({
    queryKey: ['activity-report'],
    queryFn: () => reportsApi.getActivity(undefined, 50),
  });

  const exportMutation = useMutation({
    mutationFn: (type: 'performers' | 'volunteers' | 'attendees' | 'transactions' | 'tickets') =>
      reportsApi.exportData(type),
    onSuccess: (data) => {
      const blob = new Blob([data.content], { type: data.mime_type });
      const url = URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = url;
      a.download = data.filename;
      document.body.appendChild(a);
      a.click();
      document.body.removeChild(a);
      URL.revokeObjectURL(url);
    },
  });

  const isLoading = ticketsLoading || revenueLoading || activityLoading;

  if (isLoading) {
    return (
      <div className="animate-pulse space-y-6">
        <div className="h-8 bg-gray-200 rounded-sm w-48" />
        <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
          {[...Array(4)].map((_, i) => (
            <div key={i} className="h-24 bg-gray-200 rounded-xl" />
          ))}
        </div>
        <div className="h-80 bg-gray-200 rounded-xl" />
      </div>
    );
  }

  // Calculate summary stats from ticket sales
  const totalTickets = ticketSales?.by_show.reduce((sum, s) => sum + (s.total_quantity || 0), 0) || 0;
  const totalRevenue = ticketSales?.by_show.reduce((sum, s) => sum + (s.total_revenue || 0), 0) || 0;
  const totalCheckedIn = ticketSales?.by_show.reduce((sum, s) => sum + (s.checked_in || 0), 0) || 0;
  const checkInRate = totalTickets > 0 ? Math.round((totalCheckedIn / totalTickets) * 100) : 0;

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between flex-wrap gap-4">
        <h1 className="text-2xl font-bold text-gray-900">Reports</h1>

        <div className="flex items-center gap-2">
          <span className="text-sm text-gray-500">Export:</span>
          {(['performers', 'volunteers', 'attendees', 'tickets', 'transactions'] as const).map(type => (
            <button
              key={type}
              onClick={() => exportMutation.mutate(type)}
              disabled={exportMutation.isPending}
              className="btn btn-secondary text-xs py-1.5 px-2 capitalize"
            >
              <FileSpreadsheet className="w-3 h-3" />
              {type}
            </button>
          ))}
        </div>
      </div>

      {/* Summary Cards */}
      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div className="card p-4">
          <div className="flex items-center gap-3">
            <div className="p-2 rounded-lg bg-blue-100">
              <Ticket className="w-5 h-5 text-blue-600" />
            </div>
            <div>
              <p className="text-sm text-gray-500">Tickets Sold</p>
              <p className="text-xl font-bold text-gray-900">{totalTickets.toLocaleString()}</p>
            </div>
          </div>
        </div>

        <div className="card p-4">
          <div className="flex items-center gap-3">
            <div className="p-2 rounded-lg bg-green-100">
              <DollarSign className="w-5 h-5 text-green-600" />
            </div>
            <div>
              <p className="text-sm text-gray-500">Ticket Revenue</p>
              <p className="text-xl font-bold text-green-600">${totalRevenue.toLocaleString()}</p>
            </div>
          </div>
        </div>

        <div className="card p-4">
          <div className="flex items-center gap-3">
            <div className="p-2 rounded-lg bg-purple-100">
              <Users className="w-5 h-5 text-purple-600" />
            </div>
            <div>
              <p className="text-sm text-gray-500">Checked In</p>
              <p className="text-xl font-bold text-gray-900">{totalCheckedIn.toLocaleString()}</p>
            </div>
          </div>
        </div>

        <div className="card p-4">
          <div className="flex items-center gap-3">
            <div className="p-2 rounded-lg bg-amber-100">
              <TrendingUp className="w-5 h-5 text-amber-600" />
            </div>
            <div>
              <p className="text-sm text-gray-500">Check-in Rate</p>
              <p className="text-xl font-bold text-gray-900">{checkInRate}%</p>
            </div>
          </div>
        </div>
      </div>

      {/* Tab Navigation */}
      <div className="border-b border-gray-200">
        <nav className="flex gap-4" aria-label="Report tabs">
          <button
            onClick={() => setActiveTab('tickets')}
            className={`pb-3 px-1 text-sm font-medium border-b-2 transition-colors ${
              activeTab === 'tickets'
                ? 'border-primary-600 text-primary-600'
                : 'border-transparent text-gray-500 hover:text-gray-700'
            }`}
          >
            <BarChart3 className="w-4 h-4 inline mr-1.5" />
            Ticket Sales
          </button>
          <button
            onClick={() => setActiveTab('revenue')}
            className={`pb-3 px-1 text-sm font-medium border-b-2 transition-colors ${
              activeTab === 'revenue'
                ? 'border-primary-600 text-primary-600'
                : 'border-transparent text-gray-500 hover:text-gray-700'
            }`}
          >
            <DollarSign className="w-4 h-4 inline mr-1.5" />
            Revenue
          </button>
          <button
            onClick={() => setActiveTab('activity')}
            className={`pb-3 px-1 text-sm font-medium border-b-2 transition-colors ${
              activeTab === 'activity'
                ? 'border-primary-600 text-primary-600'
                : 'border-transparent text-gray-500 hover:text-gray-700'
            }`}
          >
            <Clock className="w-4 h-4 inline mr-1.5" />
            Activity Log
          </button>
        </nav>
      </div>

      {/* Ticket Sales Tab */}
      {activeTab === 'tickets' && (
        <div className="space-y-6">
          {/* Period Selector */}
          <div className="flex items-center gap-2">
            <span className="text-sm text-gray-500">View by:</span>
            {(['daily', 'weekly', 'monthly'] as const).map(p => (
              <button
                key={p}
                onClick={() => setPeriod(p)}
                className={`px-3 py-1.5 text-sm rounded-lg transition-colors ${
                  period === p
                    ? 'bg-primary-100 text-primary-700'
                    : 'bg-gray-100 text-gray-600 hover:bg-gray-200'
                }`}
              >
                {p.charAt(0).toUpperCase() + p.slice(1)}
              </button>
            ))}
          </div>

          {/* Sales Over Time Chart */}
          <div className="card p-6">
            <h2 className="text-lg font-semibold mb-4">Sales Over Time</h2>
            <BarChart
              data={ticketSales?.over_time || []}
              labelKey="period"
              valueKey="total_revenue"
              color="#7c3aed"
            />
          </div>

          {/* Sales by Show Table */}
          <div className="card p-6">
            <h2 className="text-lg font-semibold mb-4">Sales by Show</h2>
            <div className="overflow-x-auto">
              <table className="table">
                <thead>
                  <tr>
                    <th>Show</th>
                    <th>Date</th>
                    <th className="text-right">Tickets</th>
                    <th className="text-right">Revenue</th>
                    <th className="text-right">Checked In</th>
                    <th className="text-right">Rate</th>
                  </tr>
                </thead>
                <tbody>
                  {ticketSales?.by_show.map(show => (
                    <tr key={show.id}>
                      <td className="font-medium">{show.title}</td>
                      <td className="text-gray-500">
                        {show.show_date ? new Date(show.show_date).toLocaleDateString() : '-'}
                      </td>
                      <td className="text-right">{show.total_quantity || 0}</td>
                      <td className="text-right text-green-600">
                        ${(show.total_revenue || 0).toLocaleString()}
                      </td>
                      <td className="text-right">{show.checked_in || 0}</td>
                      <td className="text-right">
                        {show.total_quantity
                          ? Math.round((show.checked_in / show.total_quantity) * 100)
                          : 0}%
                      </td>
                    </tr>
                  ))}
                  {(!ticketSales?.by_show || ticketSales.by_show.length === 0) && (
                    <tr>
                      <td colSpan={6} className="text-center text-gray-500">
                        No sales data available
                      </td>
                    </tr>
                  )}
                </tbody>
              </table>
            </div>
          </div>
        </div>
      )}

      {/* Revenue Tab */}
      {activeTab === 'revenue' && (
        <div className="space-y-6">
          {/* Revenue Summary */}
          <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div className="card p-4 border-l-4 border-green-500">
              <p className="text-sm text-gray-500">Total Income</p>
              <p className="text-2xl font-bold text-green-600">
                ${(revenue?.summary.total_income || 0).toLocaleString()}
              </p>
            </div>
            <div className="card p-4 border-l-4 border-red-500">
              <p className="text-sm text-gray-500">Total Expenses</p>
              <p className="text-2xl font-bold text-red-600">
                ${(revenue?.summary.total_expenses || 0).toLocaleString()}
              </p>
            </div>
            <div className="card p-4 border-l-4 border-blue-500">
              <p className="text-sm text-gray-500">Net Balance</p>
              <p className={`text-2xl font-bold ${
                (revenue?.summary.net || 0) >= 0 ? 'text-green-600' : 'text-red-600'
              }`}>
                ${(revenue?.summary.net || 0).toLocaleString()}
              </p>
            </div>
          </div>

          {/* Revenue Over Time Chart */}
          <div className="card p-6">
            <h2 className="text-lg font-semibold mb-4">Income vs Expenses Over Time</h2>
            <LineChart data={revenue?.over_time || []} />
          </div>

          {/* Revenue by Category */}
          <div className="card p-6">
            <h2 className="text-lg font-semibold mb-4">By Category</h2>
            <div className="overflow-x-auto">
              <table className="table">
                <thead>
                  <tr>
                    <th>Category</th>
                    <th>Type</th>
                    <th className="text-right">Amount</th>
                  </tr>
                </thead>
                <tbody>
                  {revenue?.by_category.map((item, i) => (
                    <tr key={i}>
                      <td className="capitalize font-medium">
                        {item.category?.replace('_', ' ') || 'Uncategorized'}
                      </td>
                      <td>
                        <span className={`badge ${
                          item.transaction_type === 'income' ? 'badge-success' : 'badge-error'
                        }`}>
                          {item.transaction_type}
                        </span>
                      </td>
                      <td className={`text-right font-medium ${
                        item.transaction_type === 'income' ? 'text-green-600' : 'text-red-600'
                      }`}>
                        ${Number(item.total || 0).toLocaleString()}
                      </td>
                    </tr>
                  ))}
                  {(!revenue?.by_category || revenue.by_category.length === 0) && (
                    <tr>
                      <td colSpan={3} className="text-center text-gray-500">
                        No transaction data available
                      </td>
                    </tr>
                  )}
                </tbody>
              </table>
            </div>
          </div>
        </div>
      )}

      {/* Activity Tab */}
      {activeTab === 'activity' && (
        <div className="card p-6">
          <h2 className="text-lg font-semibold mb-4">Recent Activity</h2>
          <div className="space-y-3">
            {activity?.map(log => (
              <div key={log.id} className="flex items-start gap-3 p-3 bg-gray-50 rounded-lg">
                <div className="p-2 rounded-full bg-gray-200">
                  <Clock className="w-4 h-4 text-gray-600" />
                </div>
                <div className="flex-1 min-w-0">
                  <p className="text-sm font-medium text-gray-900">
                    {log.action}
                    {log.entity_type && (
                      <span className="text-gray-500"> - {log.entity_type}</span>
                    )}
                  </p>
                  {log.details && (
                    <p className="text-sm text-gray-500 truncate">{log.details}</p>
                  )}
                  <p className="text-xs text-gray-400 mt-1">
                    {log.user_name && `by ${log.user_name} · `}
                    {new Date(log.created_at).toLocaleString()}
                  </p>
                </div>
              </div>
            ))}
            {(!activity || activity.length === 0) && (
              <p className="text-center text-gray-500 py-8">
                No activity recorded yet
              </p>
            )}
          </div>
        </div>
      )}
    </div>
  );
}
