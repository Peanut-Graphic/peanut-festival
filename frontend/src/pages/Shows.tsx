import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { showsApi } from '@/api/endpoints';
import { useFilterStore } from '@/stores/useFilterStore';
import { useToast } from '@/components/common/Toast';
import { ConfirmDialog } from '@/components/common';
import { ShowForm } from '@/components/shows/ShowForm';
import { Plus, Edit2, Trash2, Calendar, MapPin, Users, DollarSign } from 'lucide-react';
import type { Show } from '@/types';

const statusColors: Record<string, string> = {
  draft: 'badge-gray',
  scheduled: 'badge-blue',
  on_sale: 'badge-green',
  sold_out: 'badge-purple',
  completed: 'badge-gray',
  cancelled: 'badge-red',
};

export function Shows() {
  const [editingShow, setEditingShow] = useState<Show | null>(null);
  const [isModalOpen, setIsModalOpen] = useState(false);
  const [deleteConfirm, setDeleteConfirm] = useState<Show | null>(null);
  const queryClient = useQueryClient();
  const { addToast } = useToast();
  const { showFilters, setShowFilters } = useFilterStore();

  const { data: shows = [], isLoading } = useQuery({
    queryKey: ['shows', showFilters],
    queryFn: () => showsApi.getAll(showFilters),
  });

  const deleteMutation = useMutation({
    mutationFn: showsApi.delete,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['shows'] });
      addToast('success', 'Show deleted successfully');
      setDeleteConfirm(null);
    },
    onError: (error: Error) => {
      addToast('error', error.message);
    },
  });

  const handleDelete = (show: Show) => {
    setDeleteConfirm(show);
  };

  const confirmDelete = () => {
    if (deleteConfirm) {
      deleteMutation.mutate(deleteConfirm.id);
    }
  };

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
        <h1 className="text-2xl font-bold text-gray-900">Shows</h1>
        <button
          onClick={() => {
            setEditingShow(null);
            setIsModalOpen(true);
          }}
          className="btn btn-primary"
        >
          <Plus className="w-4 h-4" />
          Add Show
        </button>
      </div>

      {/* Filters */}
      <div className="card p-4">
        <div className="flex flex-wrap gap-4">
          <select
            className="input w-40"
            value={showFilters.status || ''}
            onChange={(e) => setShowFilters({ status: e.target.value || undefined })}
          >
            <option value="">All Status</option>
            <option value="draft">Draft</option>
            <option value="scheduled">Scheduled</option>
            <option value="on_sale">On Sale</option>
            <option value="sold_out">Sold Out</option>
            <option value="completed">Completed</option>
            <option value="cancelled">Cancelled</option>
          </select>
        </div>
      </div>

      {/* Shows Grid */}
      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        {shows.length === 0 ? (
          <div className="col-span-full text-center py-12 text-gray-500">
            No shows found. Create your first show to get started.
          </div>
        ) : (
          shows.map((show) => (
            <div key={show.id} className="card overflow-hidden">
              <div className="p-6">
                <div className="flex items-start justify-between mb-3">
                  <h3 className="font-semibold text-gray-900">{show.title}</h3>
                  <span className={`badge ${statusColors[show.status]}`}>{show.status}</span>
                </div>

                <div className="space-y-2 text-sm text-gray-500">
                  <div className="flex items-center gap-2">
                    <Calendar className="w-4 h-4" />
                    {new Date(show.show_date).toLocaleDateString()}
                    {show.start_time && ` at ${show.start_time}`}
                  </div>
                  {show.venue_name && (
                    <div className="flex items-center gap-2">
                      <MapPin className="w-4 h-4" />
                      {show.venue_name}
                    </div>
                  )}
                  {show.capacity && (
                    <div className="flex items-center gap-2">
                      <Users className="w-4 h-4" />
                      Capacity: {show.capacity}
                    </div>
                  )}
                </div>

                {show.ticket_price && (
                  <div className="flex items-center gap-2 text-sm text-gray-500 mt-2">
                    <DollarSign className="w-4 h-4" />
                    ${Number(show.ticket_price).toFixed(2)}
                  </div>
                )}

                <div className="flex items-center gap-2 mt-4 pt-4 border-t">
                  <button
                    onClick={() => {
                      setEditingShow(show);
                      setIsModalOpen(true);
                    }}
                    className="btn btn-ghost text-sm"
                  >
                    <Edit2 className="w-4 h-4" />
                    Edit
                  </button>
                  <button
                    onClick={() => handleDelete(show)}
                    className="btn btn-ghost text-sm text-red-600"
                  >
                    <Trash2 className="w-4 h-4" />
                    Delete
                  </button>
                </div>
              </div>
            </div>
          ))
        )}
      </div>

      {/* Show Form Modal */}
      <ShowForm
        isOpen={isModalOpen}
        onClose={() => {
          setIsModalOpen(false);
          setEditingShow(null);
        }}
        show={editingShow}
      />

      {/* Delete Confirmation */}
      <ConfirmDialog
        isOpen={!!deleteConfirm}
        onClose={() => setDeleteConfirm(null)}
        onConfirm={confirmDelete}
        title="Delete Show"
        message={`Are you sure you want to delete "${deleteConfirm?.title}"? This will also remove all ticket data associated with this show.`}
        confirmText="Delete Show"
        variant="danger"
        isLoading={deleteMutation.isPending}
      />
    </div>
  );
}
