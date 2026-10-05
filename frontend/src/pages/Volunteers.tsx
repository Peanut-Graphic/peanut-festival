import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { volunteersApi } from '@/api/endpoints';
import { useFilterStore } from '@/stores/useFilterStore';
import { useToast } from '@/components/common/Toast';
import { ConfirmDialog } from '@/components/common';
import { VolunteerForm } from '@/components/volunteers/VolunteerForm';
import { ShiftForm } from '@/components/volunteers/ShiftForm';
import { Plus, Edit2, Trash2, Search, Clock, Calendar, MapPin, Check, X } from 'lucide-react';
import type { Volunteer, VolunteerShift } from '@/types';

const statusColors: Record<string, string> = {
  applied: 'badge-yellow',
  approved: 'badge-blue',
  active: 'badge-green',
  inactive: 'badge-gray',
  declined: 'badge-red',
};

export function Volunteers() {
  const [activeTab, setActiveTab] = useState<'volunteers' | 'shifts'>('volunteers');
  const [editingVolunteer, setEditingVolunteer] = useState<Volunteer | null>(null);
  const [editingShift, setEditingShift] = useState<VolunteerShift | null>(null);
  const [isVolunteerModalOpen, setIsVolunteerModalOpen] = useState(false);
  const [isShiftModalOpen, setIsShiftModalOpen] = useState(false);
  const [deleteConfirm, setDeleteConfirm] = useState<Volunteer | null>(null);
  const [searchQuery, setSearchQuery] = useState('');
  const queryClient = useQueryClient();
  const { addToast } = useToast();
  const { volunteerFilters, setVolunteerFilters } = useFilterStore();

  const { data: volunteers = [], isLoading: volunteersLoading } = useQuery({
    queryKey: ['volunteers', volunteerFilters],
    queryFn: () => volunteersApi.getAll(volunteerFilters),
  });

  const { data: shifts = [], isLoading: shiftsLoading } = useQuery({
    queryKey: ['volunteer-shifts'],
    queryFn: () => volunteersApi.getShifts(),
  });

  const deleteMutation = useMutation({
    mutationFn: volunteersApi.delete,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['volunteers'] });
      addToast('success', 'Volunteer deleted successfully');
      setDeleteConfirm(null);
    },
    onError: (error: Error) => {
      addToast('error', error.message);
    },
  });

  const updateStatusMutation = useMutation({
    mutationFn: ({ id, status }: { id: number; status: string }) =>
      volunteersApi.update(id, { status: status as Volunteer['status'] }),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['volunteers'] });
      addToast('success', 'Status updated');
    },
    onError: (error: Error) => {
      addToast('error', error.message);
    },
  });

  const handleDelete = (volunteer: Volunteer) => {
    setDeleteConfirm(volunteer);
  };

  const confirmDelete = () => {
    if (deleteConfirm) {
      deleteMutation.mutate(deleteConfirm.id);
    }
  };

  const filteredVolunteers = volunteers.filter(
    (v) =>
      v.name.toLowerCase().includes(searchQuery.toLowerCase()) ||
      v.email.toLowerCase().includes(searchQuery.toLowerCase())
  );

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-bold text-gray-900">Volunteers</h1>
        <button
          onClick={() => {
            if (activeTab === 'volunteers') {
              setEditingVolunteer(null);
              setIsVolunteerModalOpen(true);
            } else {
              setEditingShift(null);
              setIsShiftModalOpen(true);
            }
          }}
          className="btn btn-primary"
        >
          <Plus className="w-4 h-4" />
          {activeTab === 'volunteers' ? 'Add Volunteer' : 'Add Shift'}
        </button>
      </div>

      {/* Tabs */}
      <div className="border-b border-gray-200">
        <nav className="flex gap-4">
          <button
            onClick={() => setActiveTab('volunteers')}
            className={`pb-3 px-1 text-sm font-medium border-b-2 transition-colors ${
              activeTab === 'volunteers'
                ? 'border-primary-500 text-primary-600'
                : 'border-transparent text-gray-500 hover:text-gray-700'
            }`}
          >
            Volunteers ({volunteers.length})
          </button>
          <button
            onClick={() => setActiveTab('shifts')}
            className={`pb-3 px-1 text-sm font-medium border-b-2 transition-colors ${
              activeTab === 'shifts'
                ? 'border-primary-500 text-primary-600'
                : 'border-transparent text-gray-500 hover:text-gray-700'
            }`}
          >
            Shifts ({shifts.length})
          </button>
        </nav>
      </div>

      {activeTab === 'volunteers' ? (
        <>
          {/* Filters */}
          <div className="card p-4">
            <div className="flex flex-wrap gap-4">
              <div className="relative flex-1 min-w-[200px]">
                <Search className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" />
                <input
                  type="text"
                  placeholder="Search volunteers..."
                  className="input pl-10"
                  value={searchQuery}
                  onChange={(e) => setSearchQuery(e.target.value)}
                />
              </div>
              <select
                className="input w-40"
                value={volunteerFilters.status || ''}
                onChange={(e) => setVolunteerFilters({ status: e.target.value || undefined })}
              >
                <option value="">All Status</option>
                <option value="applied">Applied</option>
                <option value="approved">Approved</option>
                <option value="active">Active</option>
                <option value="inactive">Inactive</option>
                <option value="declined">Declined</option>
              </select>
            </div>
          </div>

          {/* Volunteers Table */}
          <div className="card overflow-hidden">
            <div className="overflow-x-auto">
              <table className="table min-w-[800px]">
              <thead>
                <tr>
                  <th style={{ minWidth: '180px' }}>Volunteer</th>
                  <th style={{ minWidth: '120px' }}>Phone</th>
                  <th style={{ minWidth: '150px' }}>Skills</th>
                  <th style={{ width: '80px' }}>Hours</th>
                  <th style={{ width: '100px' }}>Status</th>
                  <th style={{ width: '130px' }}>Actions</th>
                </tr>
              </thead>
              <tbody>
                {volunteersLoading ? (
                  <tr>
                    <td colSpan={6} className="text-center py-8">
                      Loading...
                    </td>
                  </tr>
                ) : filteredVolunteers.length === 0 ? (
                  <tr>
                    <td colSpan={6} className="text-center text-gray-500 py-8">
                      No volunteers found.
                    </td>
                  </tr>
                ) : (
                  filteredVolunteers.map((volunteer) => (
                    <tr key={volunteer.id}>
                      <td>
                        <div>
                          <div className="font-medium text-gray-900">{volunteer.name}</div>
                          <div className="text-xs text-gray-500">{volunteer.email}</div>
                        </div>
                      </td>
                      <td>{volunteer.phone || '-'}</td>
                      <td>
                        <div className="flex flex-wrap gap-1">
                          {volunteer.skills?.slice(0, 3).map((skill) => (
                            <span key={skill} className="badge badge-gray text-xs">
                              {skill}
                            </span>
                          ))}
                          {(volunteer.skills?.length ?? 0) > 3 && (
                            <span className="text-xs text-gray-400">
                              +{volunteer.skills!.length - 3}
                            </span>
                          )}
                        </div>
                      </td>
                      <td>
                        <div className="flex items-center gap-1">
                          <Clock className="w-4 h-4 text-gray-400" />
                          {volunteer.hours_completed}h
                        </div>
                      </td>
                      <td>
                        <span className={`badge ${statusColors[volunteer.status]}`}>
                          {volunteer.status}
                        </span>
                      </td>
                      <td>
                        <div className="flex items-center gap-1">
                          {volunteer.status === 'applied' && (
                            <>
                              <button
                                onClick={() =>
                                  updateStatusMutation.mutate({ id: volunteer.id, status: 'approved' })
                                }
                                className="p-1.5 rounded-sm hover:bg-green-50"
                                title="Approve"
                              >
                                <Check className="w-4 h-4 text-green-600" />
                              </button>
                              <button
                                onClick={() =>
                                  updateStatusMutation.mutate({ id: volunteer.id, status: 'declined' })
                                }
                                className="p-1.5 rounded-sm hover:bg-red-50"
                                title="Decline"
                              >
                                <X className="w-4 h-4 text-red-600" />
                              </button>
                            </>
                          )}
                          <button
                            onClick={() => {
                              setEditingVolunteer(volunteer);
                              setIsVolunteerModalOpen(true);
                            }}
                            className="p-1.5 rounded-sm hover:bg-gray-100"
                            title="Edit"
                          >
                            <Edit2 className="w-4 h-4 text-gray-500" />
                          </button>
                          <button
                            onClick={() => handleDelete(volunteer)}
                            className="p-1.5 rounded-sm hover:bg-gray-100"
                            title="Delete"
                          >
                            <Trash2 className="w-4 h-4 text-red-500" />
                          </button>
                        </div>
                      </td>
                    </tr>
                  ))
                )}
              </tbody>
            </table>
            </div>
          </div>
        </>
      ) : (
        /* Shifts View */
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
          {shiftsLoading ? (
            <div className="col-span-full text-center py-8">Loading...</div>
          ) : shifts.length === 0 ? (
            <div className="col-span-full text-center py-12 text-gray-500">
              No shifts created yet.
            </div>
          ) : (
            shifts.map((shift) => (
              <div key={shift.id} className="card p-4">
                <div className="flex items-start justify-between mb-2">
                  <h3 className="font-medium text-gray-900">{shift.task_name}</h3>
                  <span
                    className={`badge ${
                      shift.status === 'open'
                        ? 'badge-green'
                        : shift.status === 'filled'
                          ? 'badge-blue'
                          : 'badge-gray'
                    }`}
                  >
                    {shift.status}
                  </span>
                </div>
                {shift.description && (
                  <p className="text-sm text-gray-500 mb-3">{shift.description}</p>
                )}
                <div className="space-y-1 text-sm text-gray-500">
                  <div className="flex items-center gap-2">
                    <Calendar className="w-4 h-4" />
                    {new Date(shift.shift_date).toLocaleDateString()}
                  </div>
                  <div className="flex items-center gap-2">
                    <Clock className="w-4 h-4" />
                    {shift.start_time} - {shift.end_time}
                  </div>
                  {shift.location && (
                    <div className="flex items-center gap-2">
                      <MapPin className="w-4 h-4" />
                      {shift.location}
                    </div>
                  )}
                </div>
                <div className="mt-3 pt-3 border-t">
                  <div className="flex items-center justify-between text-sm">
                    <span className="text-gray-500">Slots</span>
                    <span className="font-medium">
                      {shift.slots_filled} / {shift.slots_total}
                    </span>
                  </div>
                  <div className="mt-2 h-2 bg-gray-200 rounded-full overflow-hidden">
                    <div
                      className="h-full bg-primary-500 rounded-full"
                      style={{ width: `${(shift.slots_filled / shift.slots_total) * 100}%` }}
                    />
                  </div>
                </div>
              </div>
            ))
          )}
        </div>
      )}

      {/* Volunteer Form Modal */}
      <VolunteerForm
        isOpen={isVolunteerModalOpen}
        onClose={() => {
          setIsVolunteerModalOpen(false);
          setEditingVolunteer(null);
        }}
        volunteer={editingVolunteer}
      />

      {/* Shift Form Modal */}
      <ShiftForm
        isOpen={isShiftModalOpen}
        onClose={() => {
          setIsShiftModalOpen(false);
          setEditingShift(null);
        }}
        shift={editingShift}
      />

      {/* Delete Confirmation */}
      <ConfirmDialog
        isOpen={!!deleteConfirm}
        onClose={() => setDeleteConfirm(null)}
        onConfirm={confirmDelete}
        title="Delete Volunteer"
        message={`Are you sure you want to delete "${deleteConfirm?.name}"? This action cannot be undone.`}
        confirmText="Delete Volunteer"
        variant="danger"
        isLoading={deleteMutation.isPending}
      />
    </div>
  );
}
