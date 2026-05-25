import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import {
  Ticket,
  Users,
  Search,
  CheckCircle,
  Eye,
  X,
  Plus,
  Tag,
  Percent,
  DollarSign,
} from 'lucide-react';
import { client } from '../api/client';
import { useToast } from '../components/common/Toast';
import type { Attendee, Ticket as TicketType, Coupon } from '../types';

export function Attendees() {
  const [activeTab, setActiveTab] = useState<'attendees' | 'coupons'>('attendees');
  const [search, setSearch] = useState('');
  const [selectedAttendee, setSelectedAttendee] = useState<Attendee | null>(null);
  const [isCreatingCoupon, setIsCreatingCoupon] = useState(false);

  const { addToast } = useToast();
  const queryClient = useQueryClient();

  // Fetch attendees
  const { data: attendees = [], isLoading: attendeesLoading } = useQuery({
    queryKey: ['attendees', search],
    queryFn: async () => {
      const res = await client.get<{ data: Attendee[] }>('/attendees', {
        params: { search },
      });
      return res.data.data;
    },
  });

  // Fetch coupons
  const { data: coupons = [], isLoading: couponsLoading } = useQuery({
    queryKey: ['coupons'],
    queryFn: async () => {
      const res = await client.get<{ data: Coupon[] }>('/coupons');
      return res.data.data;
    },
    enabled: activeTab === 'coupons',
  });

  // Create coupon mutation
  const createCouponMutation = useMutation({
    mutationFn: (data: Partial<Coupon>) => client.post('/coupons', data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['coupons'] });
      setIsCreatingCoupon(false);
      addToast('success', 'Coupon created successfully');
    },
    onError: () => addToast('error', 'Failed to create coupon'),
  });

  const filteredAttendees = attendees.filter(
    (a) =>
      a.name.toLowerCase().includes(search.toLowerCase()) ||
      a.email.toLowerCase().includes(search.toLowerCase())
  );

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-bold text-gray-900">Attendees & Tickets</h1>
        {activeTab === 'coupons' && (
          <button
            onClick={() => setIsCreatingCoupon(true)}
            className="inline-flex items-center gap-2 px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700"
          >
            <Plus className="w-5 h-5" />
            Add Coupon
          </button>
        )}
      </div>

      {/* Tabs */}
      <div className="border-b border-gray-200">
        <nav className="-mb-px flex gap-6">
          <button
            onClick={() => setActiveTab('attendees')}
            className={`py-3 px-1 border-b-2 font-medium text-sm ${
              activeTab === 'attendees'
                ? 'border-primary-500 text-primary-600'
                : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
            }`}
          >
            <Users className="w-4 h-4 inline mr-2" />
            Attendees ({attendees.length})
          </button>
          <button
            onClick={() => setActiveTab('coupons')}
            className={`py-3 px-1 border-b-2 font-medium text-sm ${
              activeTab === 'coupons'
                ? 'border-primary-500 text-primary-600'
                : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
            }`}
          >
            <Tag className="w-4 h-4 inline mr-2" />
            Coupons ({coupons.length})
          </button>
        </nav>
      </div>

      {/* Attendees Tab */}
      {activeTab === 'attendees' && (
        <div className="space-y-4">
          {/* Search */}
          <div className="bg-white rounded-xl border border-gray-200 p-4">
            <div className="relative">
              <Search className="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400" />
              <input
                type="text"
                placeholder="Search attendees..."
                value={search}
                onChange={(e) => setSearch(e.target.value)}
                style={{ paddingLeft: '2.5rem' }}
                className="w-full pr-4 py-2 text-gray-900 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
              />
            </div>
          </div>

          {/* Attendees List */}
          <div className="bg-white rounded-xl border border-gray-200 overflow-hidden">
            {attendeesLoading ? (
              <div className="text-center py-12 text-gray-500">Loading attendees...</div>
            ) : filteredAttendees.length === 0 ? (
              <div className="text-center py-12 text-gray-500">No attendees found.</div>
            ) : (
              <div className="overflow-x-auto">
                <table className="min-w-full divide-y divide-gray-200">
                  <thead className="bg-gray-50">
                    <tr>
                      <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                        Attendee
                      </th>
                      <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                        Email
                      </th>
                      <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                        Phone
                      </th>
                      <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                        Registered
                      </th>
                      <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                        Actions
                      </th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-gray-200">
                    {filteredAttendees.map((attendee) => (
                      <tr key={attendee.id}>
                        <td className="px-4 py-3">
                          <div className="flex items-center gap-3">
                            <div className="w-10 h-10 rounded-full bg-primary-100 flex items-center justify-center">
                              <span className="text-primary-700 font-medium">
                                {attendee.name.charAt(0).toUpperCase()}
                              </span>
                            </div>
                            <span className="font-medium text-gray-900">{attendee.name}</span>
                          </div>
                        </td>
                        <td className="px-4 py-3 text-sm text-gray-600">{attendee.email}</td>
                        <td className="px-4 py-3 text-sm text-gray-600">
                          {attendee.phone || '-'}
                        </td>
                        <td className="px-4 py-3 text-sm text-gray-600">
                          {new Date(attendee.created_at).toLocaleDateString()}
                        </td>
                        <td className="px-4 py-3">
                          <button
                            onClick={() => setSelectedAttendee(attendee)}
                            className="inline-flex items-center gap-1 px-3 py-1.5 text-sm border border-gray-300 rounded-lg hover:bg-gray-50"
                          >
                            <Eye className="w-4 h-4" />
                            View Tickets
                          </button>
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            )}
          </div>
        </div>
      )}

      {/* Coupons Tab */}
      {activeTab === 'coupons' && (
        <div className="bg-white rounded-xl border border-gray-200 overflow-hidden">
          {couponsLoading ? (
            <div className="text-center py-12 text-gray-500">Loading coupons...</div>
          ) : coupons.length === 0 ? (
            <div className="text-center py-12">
              <Tag className="w-12 h-12 mx-auto text-gray-400 mb-4" />
              <p className="text-gray-600">No coupons yet.</p>
              <p className="text-sm text-gray-500 mt-1">
                Create coupons for discounts on tickets.
              </p>
            </div>
          ) : (
            <div className="overflow-x-auto">
              <table className="min-w-full divide-y divide-gray-200">
                <thead className="bg-gray-50">
                  <tr>
                    <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                      Code
                    </th>
                    <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                      Discount
                    </th>
                    <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                      Usage
                    </th>
                    <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                      Valid Until
                    </th>
                    <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                      Status
                    </th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-gray-200">
                  {coupons.map((coupon) => (
                    <tr key={coupon.id}>
                      <td className="px-4 py-3">
                        <code className="px-2 py-1 bg-gray-100 rounded text-sm font-mono">
                          {coupon.code}
                        </code>
                      </td>
                      <td className="px-4 py-3 text-sm">
                        {coupon.discount_type === 'percentage' ? (
                          <span className="inline-flex items-center gap-1">
                            <Percent className="w-4 h-4" />
                            {coupon.discount_value}%
                          </span>
                        ) : (
                          <span className="inline-flex items-center gap-1">
                            <DollarSign className="w-4 h-4" />
                            {coupon.discount_value}
                          </span>
                        )}
                      </td>
                      <td className="px-4 py-3 text-sm text-gray-600">
                        {coupon.times_used}
                        {coupon.max_uses && ` / ${coupon.max_uses}`}
                      </td>
                      <td className="px-4 py-3 text-sm text-gray-600">
                        {coupon.valid_until
                          ? new Date(coupon.valid_until).toLocaleDateString()
                          : 'No expiry'}
                      </td>
                      <td className="px-4 py-3">
                        <span
                          className={`px-2 py-1 text-xs font-medium rounded ${
                            coupon.status === 'active'
                              ? 'bg-green-100 text-green-700'
                              : coupon.status === 'expired'
                                ? 'bg-yellow-100 text-yellow-700'
                                : 'bg-gray-100 text-gray-700'
                          }`}
                        >
                          {coupon.status}
                        </span>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </div>
      )}

      {/* Attendee Tickets Modal */}
      {selectedAttendee && (
        <AttendeeTicketsModal
          attendee={selectedAttendee}
          onClose={() => setSelectedAttendee(null)}
        />
      )}

      {/* Create Coupon Modal */}
      {isCreatingCoupon && (
        <CreateCouponModal
          onClose={() => setIsCreatingCoupon(false)}
          onSave={(data) => createCouponMutation.mutate(data)}
          isSaving={createCouponMutation.isPending}
        />
      )}
    </div>
  );
}

// Attendee Tickets Modal
function AttendeeTicketsModal({
  attendee,
  onClose,
}: {
  attendee: Attendee;
  onClose: () => void;
}) {
  const { addToast } = useToast();
  const queryClient = useQueryClient();

  const { data: tickets = [], isLoading } = useQuery({
    queryKey: ['attendee-tickets', attendee.id],
    queryFn: async () => {
      const res = await client.get<{ data: TicketType[] }>(
        `/attendees/${attendee.id}/tickets`
      );
      return res.data.data;
    },
  });

  const checkInMutation = useMutation({
    mutationFn: (ticketId: number) => client.post(`/tickets/${ticketId}/check-in`),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['attendee-tickets', attendee.id] });
      addToast('success', 'Ticket checked in');
    },
    onError: () => addToast('error', 'Failed to check in'),
  });

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50">
      <div className="bg-white rounded-xl shadow-xl max-w-2xl w-full mx-4 max-h-[80vh] overflow-y-auto">
        <div className="flex items-center justify-between p-4 border-b">
          <div>
            <h2 className="text-lg font-semibold">{attendee.name}</h2>
            <p className="text-sm text-gray-500">{attendee.email}</p>
          </div>
          <button onClick={onClose} className="p-2 hover:bg-gray-100 rounded-lg">
            <X className="w-5 h-5" />
          </button>
        </div>

        <div className="p-4">
          {isLoading ? (
            <div className="text-center py-8 text-gray-500">Loading tickets...</div>
          ) : tickets.length === 0 ? (
            <div className="text-center py-8 text-gray-500">No tickets found.</div>
          ) : (
            <div className="space-y-3">
              {tickets.map((ticket) => (
                <div
                  key={ticket.id}
                  className="flex items-center justify-between p-4 border rounded-lg"
                >
                  <div className="flex items-center gap-4">
                    <div
                      className={`w-10 h-10 rounded-full flex items-center justify-center ${
                        ticket.checked_in
                          ? 'bg-green-100 text-green-600'
                          : 'bg-gray-100 text-gray-600'
                      }`}
                    >
                      <Ticket className="w-5 h-5" />
                    </div>
                    <div>
                      <p className="font-medium text-gray-900">
                        {ticket.show_title || 'General Admission'}
                      </p>
                      <p className="text-sm text-gray-500">
                        {ticket.show_date
                          ? new Date(ticket.show_date).toLocaleDateString()
                          : 'Festival Pass'}
                        {ticket.start_time && ` at ${ticket.start_time}`}
                      </p>
                      {ticket.ticket_type && (
                        <p className="text-xs text-gray-400">{ticket.ticket_type}</p>
                      )}
                    </div>
                  </div>
                  <div className="flex items-center gap-3">
                    {ticket.checked_in ? (
                      <span className="inline-flex items-center gap-1 px-3 py-1.5 text-sm bg-green-100 text-green-700 rounded-lg">
                        <CheckCircle className="w-4 h-4" />
                        Checked In
                      </span>
                    ) : (
                      <button
                        onClick={() => checkInMutation.mutate(ticket.id)}
                        disabled={checkInMutation.isPending}
                        className="inline-flex items-center gap-1 px-3 py-1.5 text-sm bg-primary-600 text-white rounded-lg hover:bg-primary-700 disabled:opacity-50"
                      >
                        <CheckCircle className="w-4 h-4" />
                        Check In
                      </button>
                    )}
                  </div>
                </div>
              ))}
            </div>
          )}
        </div>
      </div>
    </div>
  );
}

// Create Coupon Modal
function CreateCouponModal({
  onClose,
  onSave,
  isSaving,
}: {
  onClose: () => void;
  onSave: (data: Partial<Coupon>) => void;
  isSaving: boolean;
}) {
  const [formData, setFormData] = useState({
    code: '',
    discount_type: 'percentage' as 'percentage' | 'fixed',
    discount_value: 10,
    max_uses: '',
    valid_until: '',
  });

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    if (!formData.code.trim()) return;

    onSave({
      ...formData,
      max_uses: formData.max_uses ? parseInt(formData.max_uses) : null,
      valid_until: formData.valid_until || null,
      status: 'active',
    });
  };

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50">
      <div className="bg-white rounded-xl shadow-xl max-w-md w-full mx-4">
        <form onSubmit={handleSubmit}>
          <div className="flex items-center justify-between p-4 border-b">
            <h2 className="text-lg font-semibold">Create Coupon</h2>
            <button type="button" onClick={onClose} className="p-2 hover:bg-gray-100 rounded-lg">
              <X className="w-5 h-5" />
            </button>
          </div>

          <div className="p-4 space-y-4">
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1" htmlFor="attendees-coupon-code">
                Coupon Code *
              </label>
              <input id="attendees-coupon-code"
                type="text"
                value={formData.code}
                onChange={(e) =>
                  setFormData({ ...formData, code: e.target.value.toUpperCase() })
                }
                className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 font-mono"
                placeholder="SUMMER2025"
                required
              />
            </div>

            <div className="grid grid-cols-2 gap-4">
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1" htmlFor="attendees-discount-type">
                  Discount Type
                </label>
                <select id="attendees-discount-type"
                  value={formData.discount_type}
                  onChange={(e) =>
                    setFormData({
                      ...formData,
                      discount_type: e.target.value as 'percentage' | 'fixed',
                    })
                  }
                  className="w-full px-3 py-2 border border-gray-300 rounded-lg"
                >
                  <option value="percentage">Percentage (%)</option>
                  <option value="fixed">Fixed Amount ($)</option>
                </select>
              </div>
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1" htmlFor="attendees-discount-value">
                  Discount Value
                </label>
                <input id="attendees-discount-value"
                  type="number"
                  min="0"
                  value={formData.discount_value}
                  onChange={(e) =>
                    setFormData({ ...formData, discount_value: parseInt(e.target.value) || 0 })
                  }
                  className="w-full px-3 py-2 border border-gray-300 rounded-lg"
                />
              </div>
            </div>

            <div className="grid grid-cols-2 gap-4">
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1" htmlFor="attendees-max-uses">
                  Max Uses
                </label>
                <input id="attendees-max-uses"
                  type="number"
                  min="0"
                  value={formData.max_uses}
                  onChange={(e) => setFormData({ ...formData, max_uses: e.target.value })}
                  className="w-full px-3 py-2 border border-gray-300 rounded-lg"
                  placeholder="Unlimited"
                />
              </div>
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1" htmlFor="attendees-valid-until">
                  Valid Until
                </label>
                <input id="attendees-valid-until"
                  type="date"
                  value={formData.valid_until}
                  onChange={(e) => setFormData({ ...formData, valid_until: e.target.value })}
                  className="w-full px-3 py-2 border border-gray-300 rounded-lg"
                />
              </div>
            </div>
          </div>

          <div className="flex justify-end gap-3 p-4 border-t bg-gray-50">
            <button
              type="button"
              onClick={onClose}
              className="px-4 py-2 text-gray-700 hover:bg-gray-100 rounded-lg"
            >
              Cancel
            </button>
            <button
              type="submit"
              disabled={isSaving || !formData.code.trim()}
              className="px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700 disabled:opacity-50"
            >
              {isSaving ? 'Creating...' : 'Create Coupon'}
            </button>
          </div>
        </form>
      </div>
    </div>
  );
}
