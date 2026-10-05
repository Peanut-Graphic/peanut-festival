import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { sponsorsApi } from '@/api/endpoints';
import { useFilterStore } from '@/stores/useFilterStore';
import { useToast } from '@/components/common/Toast';
import { Plus, Edit2, Trash2, ExternalLink, DollarSign, FileCheck, CreditCard } from 'lucide-react';
import type { Sponsor } from '@/types';

const statusColors: Record<string, string> = {
  prospect: 'badge-gray',
  negotiating: 'badge-yellow',
  confirmed: 'badge-green',
  declined: 'badge-red',
  past: 'badge-purple',
};

const tierColors: Record<string, string> = {
  presenting: 'bg-linear-to-r/srgb from-yellow-400 to-amber-500 text-white',
  gold: 'bg-yellow-500 text-white',
  silver: 'bg-gray-400 text-white',
  bronze: 'bg-amber-700 text-white',
  in_kind: 'bg-blue-500 text-white',
  media: 'bg-purple-500 text-white',
};

const tierLabels: Record<string, string> = {
  presenting: 'Presenting',
  gold: 'Gold',
  silver: 'Silver',
  bronze: 'Bronze',
  in_kind: 'In-Kind',
  media: 'Media',
};

export function Sponsors() {
  const [editingSponsor, setEditingSponsor] = useState<Sponsor | null>(null);
  const [isModalOpen, setIsModalOpen] = useState(false);
  const queryClient = useQueryClient();
  const { addToast } = useToast();
  const { sponsorFilters, setSponsorFilters } = useFilterStore();

  const { data: sponsors = [], isLoading } = useQuery({
    queryKey: ['sponsors', sponsorFilters],
    queryFn: () => sponsorsApi.getAll(sponsorFilters),
  });

  const deleteMutation = useMutation({
    mutationFn: sponsorsApi.delete,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['sponsors'] });
      addToast('success', 'Sponsor deleted successfully');
    },
    onError: (error: Error) => {
      addToast('error', error.message);
    },
  });

  const handleDelete = (id: number) => {
    if (confirm('Are you sure you want to delete this sponsor?')) {
      deleteMutation.mutate(id);
    }
  };

  // Group sponsors by tier
  const sponsorsByTier = sponsors.reduce(
    (acc, sponsor) => {
      if (!acc[sponsor.tier]) {
        acc[sponsor.tier] = [];
      }
      acc[sponsor.tier].push(sponsor);
      return acc;
    },
    {} as Record<string, Sponsor[]>
  );

  const tierOrder = ['presenting', 'gold', 'silver', 'bronze', 'in_kind', 'media'];

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
        <h1 className="text-2xl font-bold text-gray-900">Sponsors</h1>
        <button
          onClick={() => {
            setEditingSponsor(null);
            setIsModalOpen(true);
          }}
          className="btn btn-primary"
        >
          <Plus className="w-4 h-4" />
          Add Sponsor
        </button>
      </div>

      {/* Summary Stats */}
      <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div className="pf-card p-4">
          <div className="text-sm text-gray-500">Total Sponsorship</div>
          <div className="text-2xl font-bold text-green-600">
            ${sponsors.reduce((sum, s) => sum + (s.sponsorship_amount || 0), 0).toLocaleString()}
          </div>
        </div>
        <div className="pf-card p-4">
          <div className="text-sm text-gray-500">In-Kind Value</div>
          <div className="text-2xl font-bold text-blue-600">
            ${sponsors.reduce((sum, s) => sum + (s.in_kind_value || 0), 0).toLocaleString()}
          </div>
        </div>
        <div className="pf-card p-4">
          <div className="text-sm text-gray-500">Confirmed Sponsors</div>
          <div className="text-2xl font-bold text-gray-900">
            {sponsors.filter((s) => s.status === 'confirmed').length}
          </div>
        </div>
      </div>

      {/* Filters */}
      <div className="pf-card p-4">
        <div className="flex flex-wrap gap-4">
          <select
            className="input w-40"
            value={sponsorFilters.tier || ''}
            onChange={(e) => setSponsorFilters({ tier: e.target.value || undefined })}
          >
            <option value="">All Tiers</option>
            {tierOrder.map((tier) => (
              <option key={tier} value={tier}>
                {tierLabels[tier]}
              </option>
            ))}
          </select>
          <select
            className="input w-40"
            value={sponsorFilters.status || ''}
            onChange={(e) => setSponsorFilters({ status: e.target.value || undefined })}
          >
            <option value="">All Status</option>
            <option value="prospect">Prospect</option>
            <option value="negotiating">Negotiating</option>
            <option value="confirmed">Confirmed</option>
            <option value="declined">Declined</option>
            <option value="past">Past</option>
          </select>
        </div>
      </div>

      {/* Sponsors by Tier */}
      {sponsors.length === 0 ? (
        <div className="pf-card p-6 text-center text-gray-500">
          No sponsors found. Add your first sponsor to get started.
        </div>
      ) : (
        tierOrder.map(
          (tier) =>
            sponsorsByTier[tier]?.length > 0 && (
              <div key={tier} className="space-y-4">
                <h2 className="text-lg font-semibold flex items-center gap-2">
                  <span className={`px-2 py-1 rounded-sm text-sm ${tierColors[tier]}`}>
                    {tierLabels[tier]}
                  </span>
                  <span className="text-gray-400 text-sm">
                    ({sponsorsByTier[tier].length})
                  </span>
                </h2>

                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                  {sponsorsByTier[tier].map((sponsor) => (
                    <div key={sponsor.id} className="pf-card overflow-hidden">
                      <div className="p-6">
                        <div className="flex items-start justify-between mb-3">
                          <div className="flex items-center gap-3">
                            {sponsor.logo_url ? (
                              <img
                                src={sponsor.logo_url}
                                alt={sponsor.company_name}
                                className="w-12 h-12 object-contain"
                              />
                            ) : (
                              <div className="w-12 h-12 bg-gray-100 rounded-sm flex items-center justify-center">
                                <span className="text-xl font-bold text-gray-400">
                                  {sponsor.company_name.charAt(0)}
                                </span>
                              </div>
                            )}
                            <div>
                              <h3 className="font-semibold text-gray-900">
                                {sponsor.company_name}
                              </h3>
                              {sponsor.contact_name && (
                                <p className="text-xs text-gray-500">{sponsor.contact_name}</p>
                              )}
                            </div>
                          </div>
                          <span className={`badge ${statusColors[sponsor.status]}`}>
                            {sponsor.status}
                          </span>
                        </div>

                        <div className="space-y-2 text-sm">
                          {sponsor.sponsorship_amount && (
                            <div className="flex items-center gap-2 text-green-600">
                              <DollarSign className="w-4 h-4" />
                              ${sponsor.sponsorship_amount.toLocaleString()}
                            </div>
                          )}
                          {sponsor.in_kind_value && (
                            <div className="flex items-center gap-2 text-blue-600">
                              <DollarSign className="w-4 h-4" />
                              ${sponsor.in_kind_value.toLocaleString()} in-kind
                            </div>
                          )}
                        </div>

                        <div className="flex flex-wrap gap-2 mt-3">
                          {sponsor.contract_signed && (
                            <span className="badge badge-green text-xs">
                              <FileCheck className="w-3 h-3 mr-1" />
                              Contract
                            </span>
                          )}
                          {sponsor.payment_received && (
                            <span className="badge badge-green text-xs">
                              <CreditCard className="w-3 h-3 mr-1" />
                              Paid
                            </span>
                          )}
                        </div>

                        <div className="flex items-center gap-2 mt-4 pt-4 border-t">
                          {sponsor.website && (
                            <a
                              href={sponsor.website}
                              target="_blank"
                              rel="noopener noreferrer"
                              className="btn btn-ghost text-sm"
                            >
                              <ExternalLink className="w-4 h-4" />
                              Website
                            </a>
                          )}
                          <button
                            onClick={() => {
                              setEditingSponsor(sponsor);
                              setIsModalOpen(true);
                            }}
                            className="btn btn-ghost text-sm"
                          >
                            <Edit2 className="w-4 h-4" />
                            Edit
                          </button>
                          <button
                            onClick={() => handleDelete(sponsor.id)}
                            className="btn btn-ghost text-sm text-red-600"
                          >
                            <Trash2 className="w-4 h-4" />
                          </button>
                        </div>
                      </div>
                    </div>
                  ))}
                </div>
              </div>
            )
        )
      )}

      {/* Modal placeholder */}
      {isModalOpen && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50">
          <div className="bg-white rounded-xl p-6 w-full max-w-lg max-h-[90vh] overflow-y-auto">
            <h2 className="text-lg font-semibold mb-4">
              {editingSponsor ? 'Edit Sponsor' : 'New Sponsor'}
            </h2>
            <p className="text-gray-500">Sponsor form will go here</p>
            <div className="flex justify-end gap-2 mt-6">
              <button onClick={() => setIsModalOpen(false)} className="btn btn-secondary">
                Cancel
              </button>
              <button className="btn btn-primary">Save</button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
