import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { festivalsApi } from '@/api/endpoints';
import { useToast } from '@/components/common/Toast';
import { ConfirmDialog } from '@/components/common';
import { FestivalForm } from '@/components/festivals/FestivalForm';
import { Plus, Edit2, Trash2, Calendar, Copy } from 'lucide-react';
import type { Festival } from '@/types';

const statusColors: Record<string, string> = {
  draft: 'badge-gray',
  planning: 'badge-blue',
  active: 'badge-green',
  completed: 'badge-purple',
  archived: 'badge-gray',
};

export function Festivals() {
  const [editingFestival, setEditingFestival] = useState<Festival | null>(null);
  const [isModalOpen, setIsModalOpen] = useState(false);
  const [deleteConfirm, setDeleteConfirm] = useState<Festival | null>(null);
  const queryClient = useQueryClient();
  const { addToast } = useToast();

  const { data: festivals = [], isLoading } = useQuery({
    queryKey: ['festivals'],
    queryFn: festivalsApi.getAll,
  });

  const deleteMutation = useMutation({
    mutationFn: festivalsApi.delete,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['festivals'] });
      addToast('success', 'Festival deleted successfully');
      setDeleteConfirm(null);
    },
    onError: (error: Error) => {
      addToast('error', error.message);
    },
  });

  const duplicateMutation = useMutation({
    mutationFn: async (festival: Festival) => {
      const { id, created_at, updated_at, ...data } = festival;
      return festivalsApi.create({
        ...data,
        name: `${data.name} (Copy)`,
        slug: `${data.slug}-copy`,
        status: 'draft',
      });
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['festivals'] });
      addToast('success', 'Festival duplicated successfully');
    },
    onError: (error: Error) => {
      addToast('error', error.message);
    },
  });

  const handleDelete = (festival: Festival) => {
    setDeleteConfirm(festival);
  };

  const confirmDelete = () => {
    if (deleteConfirm) {
      deleteMutation.mutate(deleteConfirm.id);
    }
  };

  if (isLoading) {
    return (
      <div className="animate-pulse space-y-4">
        <div className="h-8 bg-gray-200 rounded w-48" />
        <div className="h-64 bg-gray-200 rounded-xl" />
      </div>
    );
  }

  return (
    <div className="space-y-6">
      <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h1 className="text-2xl font-bold text-gray-900">Festivals</h1>
          <p className="mt-1 text-sm text-gray-500">
            Organize your active and upcoming festivals, then jump into schedules, performers, and venue planning.
          </p>
        </div>
        <button
          onClick={() => {
            setEditingFestival(null);
            setIsModalOpen(true);
          }}
          className="btn btn-primary"
        >
          <Plus className="w-4 h-4" />
          Add Festival
        </button>
      </div>

      <div className="card overflow-hidden">
        <div className="overflow-x-auto">
          <table className="table w-full min-w-[600px]">
          <thead>
            <tr>
              <th>Name</th>
              <th className="whitespace-nowrap">Dates</th>
              <th>Location</th>
              <th className="whitespace-nowrap">Status</th>
              <th className="whitespace-nowrap text-right">Actions</th>
            </tr>
          </thead>
          <tbody>
            {festivals.length === 0 ? (
              <tr>
                <td colSpan={5} className="text-center text-gray-500 py-8">
                  No festivals yet. Create your first festival to get started.
                </td>
              </tr>
            ) : (
              festivals.map((festival) => (
                <tr key={festival.id}>
                  <td>
                    <div className="font-medium text-gray-900">{festival.name}</div>
                    <div className="text-xs text-gray-500">{festival.slug}</div>
                  </td>
                  <td>
                    {festival.start_date && festival.end_date ? (
                      <div className="flex items-center gap-1 text-sm">
                        <Calendar className="w-4 h-4 text-gray-400" />
                        {new Date(festival.start_date).toLocaleDateString()} -{' '}
                        {new Date(festival.end_date).toLocaleDateString()}
                      </div>
                    ) : (
                      <span className="text-gray-400">Not set</span>
                    )}
                  </td>
                  <td>{festival.location || <span className="text-gray-400">Not set</span>}</td>
                  <td>
                    <span className={`badge ${statusColors[festival.status]}`}>
                      {festival.status}
                    </span>
                  </td>
                  <td className="text-right">
                    <div className="flex items-center justify-end gap-1">
                      <button
                        onClick={() => {
                          setEditingFestival(festival);
                          setIsModalOpen(true);
                        }}
                        className="p-1.5 rounded hover:bg-gray-100"
                        title="Edit"
                        aria-label={`Edit ${festival.name}`}
                      >
                        <Edit2 className="w-4 h-4 text-gray-500" />
                      </button>
                      <button
                        onClick={() => duplicateMutation.mutate(festival)}
                        className="p-1.5 rounded hover:bg-gray-100"
                        title="Duplicate"
                        aria-label={`Duplicate ${festival.name}`}
                      >
                        <Copy className="w-4 h-4 text-gray-500" />
                      </button>
                      <button
                        onClick={() => handleDelete(festival)}
                        className="p-1.5 rounded hover:bg-gray-100"
                        title="Delete"
                        aria-label={`Delete ${festival.name}`}
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

      {/* Festival Form Modal */}
      <FestivalForm
        isOpen={isModalOpen}
        onClose={() => {
          setIsModalOpen(false);
          setEditingFestival(null);
        }}
        festival={editingFestival}
      />

      {/* Delete Confirmation */}
      <ConfirmDialog
        isOpen={!!deleteConfirm}
        onClose={() => setDeleteConfirm(null)}
        onConfirm={confirmDelete}
        title="Delete Festival"
        message={`Are you sure you want to delete "${deleteConfirm?.name}"? This action cannot be undone and will remove all associated shows, performers, and data.`}
        confirmText="Delete Festival"
        variant="danger"
        isLoading={deleteMutation.isPending}
      />
    </div>
  );
}
