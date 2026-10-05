import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { venuesApi } from '@/api/endpoints';
import { useToast } from '@/components/common/Toast';
import { ConfirmDialog } from '@/components/common';
import { VenueForm } from '@/components/venues/VenueForm';
import { Plus, Edit2, Trash2, MapPin, Users, DollarSign } from 'lucide-react';
import type { Venue } from '@/types';

const statusColors: Record<string, string> = {
  active: 'badge-green',
  inactive: 'badge-gray',
  pending: 'badge-yellow',
};

const venueTypeLabels: Record<string, string> = {
  theater: 'Theater',
  bar: 'Bar',
  gallery: 'Gallery',
  outdoor: 'Outdoor',
  restaurant: 'Restaurant',
  other: 'Other',
};

export function Venues() {
  const [editingVenue, setEditingVenue] = useState<Venue | null>(null);
  const [isModalOpen, setIsModalOpen] = useState(false);
  const [deleteConfirm, setDeleteConfirm] = useState<Venue | null>(null);
  const [statusFilter, setStatusFilter] = useState<string>('');
  const [typeFilter, setTypeFilter] = useState<string>('');
  const queryClient = useQueryClient();
  const { addToast } = useToast();

  const { data: venues = [], isLoading } = useQuery({
    queryKey: ['venues', statusFilter, typeFilter],
    queryFn: () =>
      venuesApi.getAll({
        status: statusFilter || undefined,
        venue_type: typeFilter || undefined,
      }),
  });

  const deleteMutation = useMutation({
    mutationFn: venuesApi.delete,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['venues'] });
      addToast('success', 'Venue deleted successfully');
      setDeleteConfirm(null);
    },
    onError: (error: Error) => {
      addToast('error', error.message);
    },
  });

  const handleDelete = (venue: Venue) => {
    setDeleteConfirm(venue);
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
        <h1 className="text-2xl font-bold text-gray-900">Venues</h1>
        <button
          onClick={() => {
            setEditingVenue(null);
            setIsModalOpen(true);
          }}
          className="btn btn-primary"
        >
          <Plus className="w-4 h-4" />
          Add Venue
        </button>
      </div>

      {/* Filters */}
      <div className="pf-card p-4">
        <div className="flex flex-wrap gap-4">
          <select
            className="input w-40"
            value={statusFilter}
            onChange={(e) => setStatusFilter(e.target.value)}
          >
            <option value="">All Status</option>
            <option value="active">Active</option>
            <option value="inactive">Inactive</option>
            <option value="pending">Pending</option>
          </select>
          <select
            className="input w-40"
            value={typeFilter}
            onChange={(e) => setTypeFilter(e.target.value)}
          >
            <option value="">All Types</option>
            <option value="theater">Theater</option>
            <option value="bar">Bar</option>
            <option value="gallery">Gallery</option>
            <option value="outdoor">Outdoor</option>
            <option value="restaurant">Restaurant</option>
            <option value="other">Other</option>
          </select>
        </div>
      </div>

      {/* Venues Grid */}
      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        {venues.length === 0 ? (
          <div className="col-span-full text-center py-12 text-gray-500">
            No venues found. Add your first venue to get started.
          </div>
        ) : (
          venues.map((venue) => (
            <div key={venue.id} className="pf-card overflow-hidden">
              <div className="p-6">
                <div className="flex items-start justify-between mb-3">
                  <div>
                    <h3 className="font-semibold text-gray-900">{venue.name}</h3>
                    <span className="text-xs text-gray-500">
                      {venueTypeLabels[venue.venue_type]}
                    </span>
                  </div>
                  <span className={`badge ${statusColors[venue.status]}`}>{venue.status}</span>
                </div>

                <div className="space-y-2 text-sm text-gray-500">
                  {venue.address && (
                    <div className="flex items-start gap-2">
                      <MapPin className="w-4 h-4 mt-0.5 shrink-0" />
                      <span>
                        {venue.address}
                        {venue.city && `, ${venue.city}`}
                        {venue.state && `, ${venue.state}`}
                      </span>
                    </div>
                  )}
                  {venue.capacity && (
                    <div className="flex items-center gap-2">
                      <Users className="w-4 h-4" />
                      Capacity: {venue.capacity}
                    </div>
                  )}
                  {venue.rental_cost && (
                    <div className="flex items-center gap-2">
                      <DollarSign className="w-4 h-4" />
                      ${venue.rental_cost.toLocaleString()} rental
                    </div>
                  )}
                </div>

                {/* Rating */}
                {venue.rating_internal && (
                  <div className="mt-3 flex items-center gap-1">
                    {[...Array(5)].map((_, i) => (
                      <span
                        key={i}
                        className={`text-lg ${
                          i < venue.rating_internal! ? 'text-yellow-400' : 'text-gray-200'
                        }`}
                      >
                        ★
                      </span>
                    ))}
                  </div>
                )}

                <div className="flex items-center gap-2 mt-4 pt-4 border-t">
                  <button
                    onClick={() => {
                      setEditingVenue(venue);
                      setIsModalOpen(true);
                    }}
                    className="btn btn-ghost text-sm"
                  >
                    <Edit2 className="w-4 h-4" />
                    Edit
                  </button>
                  <button
                    onClick={() => handleDelete(venue)}
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

      {/* Venue Form Modal */}
      <VenueForm
        isOpen={isModalOpen}
        onClose={() => {
          setIsModalOpen(false);
          setEditingVenue(null);
        }}
        venue={editingVenue}
      />

      {/* Delete Confirmation */}
      <ConfirmDialog
        isOpen={!!deleteConfirm}
        onClose={() => setDeleteConfirm(null)}
        onConfirm={confirmDelete}
        title="Delete Venue"
        message={`Are you sure you want to delete "${deleteConfirm?.name}"? Shows assigned to this venue will need to be reassigned.`}
        confirmText="Delete Venue"
        variant="danger"
        isLoading={deleteMutation.isPending}
      />
    </div>
  );
}
