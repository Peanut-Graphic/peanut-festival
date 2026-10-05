import { useQuery } from '@tanstack/react-query';
import { dashboardApi, transactionsApi } from '@/api/endpoints';
import { DollarSign, TrendingUp, TrendingDown, PieChart } from 'lucide-react';

export function Analytics() {
  const { data: stats, isLoading: statsLoading } = useQuery({
    queryKey: ['dashboard-stats'],
    queryFn: dashboardApi.getStats,
  });

  const { data: summary, isLoading: summaryLoading } = useQuery({
    queryKey: ['transaction-summary'],
    queryFn: () => transactionsApi.getSummary(),
  });

  if (statsLoading || summaryLoading) {
    return (
      <div className="animate-pulse space-y-6">
        <div className="h-8 bg-gray-200 rounded-sm w-48" />
        <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
          {[...Array(3)].map((_, i) => (
            <div key={i} className="h-32 bg-gray-200 rounded-xl" />
          ))}
        </div>
        <div className="h-64 bg-gray-200 rounded-xl" />
      </div>
    );
  }

  const incomeCategories = Object.entries(summary?.by_category.income || {});
  const expenseCategories = Object.entries(summary?.by_category.expense || {});

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-bold text-gray-900">Analytics</h1>
      </div>

      {/* Financial Overview */}
      <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div className="card p-6">
          <div className="flex items-center gap-4">
            <div className="p-3 rounded-lg bg-green-500">
              <TrendingUp className="w-6 h-6 text-white" />
            </div>
            <div>
              <p className="text-sm text-gray-500">Total Income</p>
              <p className="text-2xl font-bold text-green-600">
                ${(summary?.total_income || 0).toLocaleString()}
              </p>
            </div>
          </div>
        </div>

        <div className="card p-6">
          <div className="flex items-center gap-4">
            <div className="p-3 rounded-lg bg-red-500">
              <TrendingDown className="w-6 h-6 text-white" />
            </div>
            <div>
              <p className="text-sm text-gray-500">Total Expenses</p>
              <p className="text-2xl font-bold text-red-600">
                ${(summary?.total_expenses || 0).toLocaleString()}
              </p>
            </div>
          </div>
        </div>

        <div className="card p-6">
          <div className="flex items-center gap-4">
            <div className="p-3 rounded-lg bg-blue-500">
              <DollarSign className="w-6 h-6 text-white" />
            </div>
            <div>
              <p className="text-sm text-gray-500">Net Balance</p>
              <p
                className={`text-2xl font-bold ${
                  (summary?.net || 0) >= 0 ? 'text-green-600' : 'text-red-600'
                }`}
              >
                ${(summary?.net || 0).toLocaleString()}
              </p>
            </div>
          </div>
        </div>
      </div>

      {/* Category Breakdown */}
      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {/* Income by Category */}
        <div className="card p-6">
          <h2 className="text-lg font-semibold mb-4 flex items-center gap-2">
            <PieChart className="w-5 h-5 text-green-500" />
            Income by Category
          </h2>
          {incomeCategories.length === 0 ? (
            <p className="text-gray-500 text-sm">No income recorded yet</p>
          ) : (
            <div className="space-y-3">
              {incomeCategories
                .sort(([, a], [, b]) => b - a)
                .map(([category, amount]) => (
                  <div key={category}>
                    <div className="flex justify-between items-center mb-1">
                      <span className="text-sm text-gray-600 capitalize">
                        {category.replace('_', ' ')}
                      </span>
                      <span className="text-sm font-medium text-green-600">
                        ${amount.toLocaleString()}
                      </span>
                    </div>
                    <div className="h-2 bg-gray-200 rounded-full overflow-hidden">
                      <div
                        className="h-full bg-green-500 rounded-full"
                        style={{
                          width: `${(amount / (summary?.total_income || 1)) * 100}%`,
                        }}
                      />
                    </div>
                  </div>
                ))}
            </div>
          )}
        </div>

        {/* Expenses by Category */}
        <div className="card p-6">
          <h2 className="text-lg font-semibold mb-4 flex items-center gap-2">
            <PieChart className="w-5 h-5 text-red-500" />
            Expenses by Category
          </h2>
          {expenseCategories.length === 0 ? (
            <p className="text-gray-500 text-sm">No expenses recorded yet</p>
          ) : (
            <div className="space-y-3">
              {expenseCategories
                .sort(([, a], [, b]) => b - a)
                .map(([category, amount]) => (
                  <div key={category}>
                    <div className="flex justify-between items-center mb-1">
                      <span className="text-sm text-gray-600 capitalize">
                        {category.replace('_', ' ')}
                      </span>
                      <span className="text-sm font-medium text-red-600">
                        ${amount.toLocaleString()}
                      </span>
                    </div>
                    <div className="h-2 bg-gray-200 rounded-full overflow-hidden">
                      <div
                        className="h-full bg-red-500 rounded-full"
                        style={{
                          width: `${(amount / (summary?.total_expenses || 1)) * 100}%`,
                        }}
                      />
                    </div>
                  </div>
                ))}
            </div>
          )}
        </div>
      </div>

      {/* Performance Metrics */}
      <div className="card p-6">
        <h2 className="text-lg font-semibold mb-4">Performance Metrics</h2>
        <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
          <div className="p-4 bg-gray-50 rounded-lg">
            <div className="text-2xl font-bold text-gray-900">
              {stats?.shows.total || 0}
            </div>
            <div className="text-sm text-gray-500">Total Shows</div>
          </div>
          <div className="p-4 bg-gray-50 rounded-lg">
            <div className="text-2xl font-bold text-gray-900">
              {stats?.shows.completed || 0}
            </div>
            <div className="text-sm text-gray-500">Shows Completed</div>
          </div>
          <div className="p-4 bg-gray-50 rounded-lg">
            <div className="text-2xl font-bold text-gray-900">
              {stats?.tickets.total_tickets || 0}
            </div>
            <div className="text-sm text-gray-500">Tickets Sold</div>
          </div>
          <div className="p-4 bg-gray-50 rounded-lg">
            <div className="text-2xl font-bold text-gray-900">
              {stats?.tickets.checked_in || 0}
            </div>
            <div className="text-sm text-gray-500">Attendees Checked In</div>
          </div>
        </div>
      </div>

      {/* Performer Stats */}
      <div className="card p-6">
        <h2 className="text-lg font-semibold mb-4">Performer Pipeline</h2>
        <div className="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-7 gap-4">
          {stats?.performers &&
            Object.entries(stats.performers).map(([status, count]) => (
              <div key={status} className="text-center p-3 bg-gray-50 rounded-lg">
                <div className="text-xl font-bold text-gray-900">{count}</div>
                <div className="text-xs text-gray-500 capitalize">
                  {status.replace('_', ' ')}
                </div>
              </div>
            ))}
        </div>
      </div>

      {/* Volunteer Stats */}
      <div className="card p-6">
        <h2 className="text-lg font-semibold mb-4">Volunteer Engagement</h2>
        <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
          <div className="p-4 bg-gray-50 rounded-lg">
            <div className="text-2xl font-bold text-gray-900">
              {stats?.volunteers.total_volunteers || 0}
            </div>
            <div className="text-sm text-gray-500">Total Volunteers</div>
          </div>
          <div className="p-4 bg-gray-50 rounded-lg">
            <div className="text-2xl font-bold text-gray-900">
              {stats?.volunteers.active_volunteers || 0}
            </div>
            <div className="text-sm text-gray-500">Active</div>
          </div>
          <div className="p-4 bg-gray-50 rounded-lg">
            <div className="text-2xl font-bold text-gray-900">
              {stats?.volunteers.total_hours || 0}
            </div>
            <div className="text-sm text-gray-500">Hours Logged</div>
          </div>
          <div className="p-4 bg-gray-50 rounded-lg">
            <div className="text-2xl font-bold text-gray-900">
              {stats?.volunteers.total_slots
                ? Math.round(
                    (stats.volunteers.filled_slots / stats.volunteers.total_slots) * 100
                  )
                : 0}
              %
            </div>
            <div className="text-sm text-gray-500">Shift Fill Rate</div>
          </div>
        </div>
      </div>
    </div>
  );
}
