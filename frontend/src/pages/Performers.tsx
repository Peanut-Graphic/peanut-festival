import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { performersApi } from '@/api/endpoints';
import { useFilterStore } from '@/stores/useFilterStore';
import { useToast } from '@/components/common/Toast';
import { ConfirmDialog } from '@/components/common';
import { PerformerForm } from '@/components/performers/PerformerForm';
import { Plus, Edit2, Trash2, Check, X, Clock, Search, Mail } from 'lucide-react';
import type { Performer, PerformerStatus } from '@/types';

function PerformerAvatar({ performer }: { performer: Performer }) {
  const [imageError, setImageError] = useState(false);

  if (!performer.photo_url || imageError) {
    return (
      <div className="w-10 h-10 rounded-full bg-linear-to-br/srgb from-primary-400 to-primary-600 flex items-center justify-center shrink-0">
        <span className="text-white text-sm font-medium">
          {performer.name.charAt(0).toUpperCase()}
        </span>
      </div>
    );
  }

  return (
    <img
      src={performer.photo_url}
      alt={performer.name}
      className="w-10 h-10 rounded-full object-cover shrink-0"
      onError={() => setImageError(true)}
      referrerPolicy="no-referrer"
    />
  );
}

const statusColors: Record<PerformerStatus, string> = {
  pending: 'badge-yellow',
  under_review: 'badge-blue',
  accepted: 'badge-green',
  rejected: 'badge-red',
  waitlisted: 'badge-purple',
  confirmed: 'badge-green',
  cancelled: 'badge-gray',
};

export function Performers() {
  const [editingPerformer, setEditingPerformer] = useState<Performer | null>(null);
  const [isModalOpen, setIsModalOpen] = useState(false);
  const [deleteConfirm, setDeleteConfirm] = useState<Performer | null>(null);
  const [searchQuery, setSearchQuery] = useState('');
  const queryClient = useQueryClient();
  const { addToast } = useToast();
  const { performerFilters, setPerformerFilters } = useFilterStore();

  const { data: performers = [], isLoading } = useQuery({
    queryKey: ['performers', performerFilters],
    queryFn: () => performersApi.getAll(performerFilters),
  });

  const deleteMutation = useMutation({
    mutationFn: performersApi.delete,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['performers'] });
      addToast('success', 'Performer deleted successfully');
      setDeleteConfirm(null);
    },
    onError: (error: Error) => {
      addToast('error', error.message);
    },
  });

  const reviewMutation = useMutation({
    mutationFn: ({ id, status, notes }: { id: number; status: string; notes?: string }) =>
      performersApi.review(id, status, notes),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['performers'] });
      addToast('success', 'Performer status updated');
    },
    onError: (error: Error) => {
      addToast('error', error.message);
    },
  });

  const notifyMutation = useMutation({
    mutationFn: performersApi.notify,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['performers'] });
      addToast('success', 'Notification sent successfully');
    },
    onError: (error: Error) => {
      addToast('error', error.message);
    },
  });

  const handleDelete = (performer: Performer) => {
    setDeleteConfirm(performer);
  };

  const confirmDelete = () => {
    if (deleteConfirm) {
      deleteMutation.mutate(deleteConfirm.id);
    }
  };

  const handleReview = (id: number, status: string) => {
    reviewMutation.mutate({ id, status });
  };

  const filteredPerformers = performers.filter(
    (p) =>
      p.name.toLowerCase().includes(searchQuery.toLowerCase()) ||
      p.email?.toLowerCase().includes(searchQuery.toLowerCase())
  );

  if (isLoading) {
    return (
      <div className="animate-pulse space-y-4">
        <div className="h-8 bg-gray-200 rounded-sm w-48" />
        <div className="h-64 bg-gray-200 rounded-xl" />
      </div>
    );
  }

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-bold text-gray-900">Performers</h1>
        <button
          onClick={() => {
            setEditingPerformer(null);
            setIsModalOpen(true);
          }}
          className="btn btn-primary"
        >
          <Plus className="w-4 h-4" />
          Add Performer
        </button>
      </div>

      {/* Filters */}
      <div className="pf-card p-4">
        <div className="flex flex-wrap gap-4">
          <div className="relative flex-1 min-w-[200px]">
            <Search className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" />
            <input
              type="text"
              placeholder="Search performers..."
              className="input pl-10"
              value={searchQuery}
              onChange={(e) => setSearchQuery(e.target.value)}
            />
          </div>
          <select
            className="input w-40"
            value={performerFilters.application_status || ''}
            onChange={(e) =>
              setPerformerFilters({
                application_status: (e.target.value as PerformerStatus) || undefined,
              })
            }
          >
            <option value="">All Status</option>
            <option value="pending">Pending</option>
            <option value="under_review">Under Review</option>
            <option value="accepted">Accepted</option>
            <option value="rejected">Rejected</option>
            <option value="waitlisted">Waitlisted</option>
            <option value="confirmed">Confirmed</option>
          </select>
        </div>
      </div>

      {/* Performers Table */}
      <div className="pf-card overflow-hidden">
        <div className="overflow-x-auto">
          <table className="table min-w-[700px]">
          <thead>
            <tr>
              <th style={{ minWidth: '200px' }}>Performer</th>
              <th style={{ minWidth: '120px' }}>Type</th>
              <th style={{ width: '110px' }}>Status</th>
              <th style={{ width: '100px' }}>Applied</th>
              <th style={{ width: '140px' }}>Actions</th>
            </tr>
          </thead>
          <tbody>
            {filteredPerformers.length === 0 ? (
              <tr>
                <td colSpan={5} className="text-center text-gray-500 py-8">
                  No performers found.
                </td>
              </tr>
            ) : (
              filteredPerformers.map((performer) => (
                <tr key={performer.id}>
                  <td>
                    <div className="flex items-center gap-3">
                      <PerformerAvatar performer={performer} />
                      <div className="min-w-0">
                        <div className="font-medium text-gray-900 truncate">{performer.name}</div>
                        <div className="text-xs text-gray-500 truncate">{performer.email}</div>
                      </div>
                    </div>
                  </td>
                  <td>{performer.performance_type || '-'}</td>
                  <td>
                    <span className={`badge ${statusColors[performer.application_status]}`}>
                      {performer.application_status.replace('_', ' ')}
                    </span>
                  </td>
                  <td>
                    {performer.application_date
                      ? new Date(performer.application_date).toLocaleDateString()
                      : '-'}
                  </td>
                  <td>
                    <div className="flex items-center gap-1">
                      {performer.application_status === 'pending' && (
                        <>
                          <button
                            onClick={() => handleReview(performer.id, 'accepted')}
                            className="p-1.5 rounded-sm hover:bg-green-50"
                            title="Accept"
                          >
                            <Check className="w-4 h-4 text-green-600" />
                          </button>
                          <button
                            onClick={() => handleReview(performer.id, 'rejected')}
                            className="p-1.5 rounded-sm hover:bg-red-50"
                            title="Reject"
                          >
                            <X className="w-4 h-4 text-red-600" />
                          </button>
                          <button
                            onClick={() => handleReview(performer.id, 'under_review')}
                            className="p-1.5 rounded-sm hover:bg-blue-50"
                            title="Mark as Under Review"
                          >
                            <Clock className="w-4 h-4 text-blue-600" />
                          </button>
                        </>
                      )}
                      {performer.application_status === 'accepted' && !performer.notification_sent && (
                        <button
                          onClick={() => notifyMutation.mutate(performer.id)}
                          className="p-1.5 rounded-sm hover:bg-blue-50"
                          title="Send Notification"
                        >
                          <Mail className="w-4 h-4 text-blue-600" />
                        </button>
                      )}
                      <button
                        onClick={() => {
                          setEditingPerformer(performer);
                          setIsModalOpen(true);
                        }}
                        className="p-1.5 rounded-sm hover:bg-gray-100"
                        title="Edit"
                      >
                        <Edit2 className="w-4 h-4 text-gray-500" />
                      </button>
                      <button
                        onClick={() => handleDelete(performer)}
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

      {/* Performer Form Modal */}
      <PerformerForm
        isOpen={isModalOpen}
        onClose={() => {
          setIsModalOpen(false);
          setEditingPerformer(null);
        }}
        performer={editingPerformer}
      />

      {/* Delete Confirmation */}
      <ConfirmDialog
        isOpen={!!deleteConfirm}
        onClose={() => setDeleteConfirm(null)}
        onConfirm={confirmDelete}
        title="Delete Performer"
        message={`Are you sure you want to delete "${deleteConfirm?.name}"? This action cannot be undone.`}
        confirmText="Delete Performer"
        variant="danger"
        isLoading={deleteMutation.isPending}
      />
    </div>
  );
}
