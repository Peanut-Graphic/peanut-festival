import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { vendorsApi } from '@/api/endpoints';
import { useFilterStore } from '@/stores/useFilterStore';
import { useToast } from '@/components/common/Toast';
import { Plus, Edit2, Trash2, Search, Store, DollarSign, Check, X } from 'lucide-react';
import type { Vendor } from '@/types';

const statusColors: Record<string, string> = {
  applied: 'badge-yellow',
  approved: 'badge-blue',
  active: 'badge-green',
  declined: 'badge-red',
  cancelled: 'badge-gray',
};

const vendorTypeLabels: Record<string, string> = {
  food: 'Food',
  merchandise: 'Merchandise',
  service: 'Service',
  sponsor: 'Sponsor',
  other: 'Other',
};

export function Vendors() {
  const [editingVendor, setEditingVendor] = useState<Vendor | null>(null);
  const [isModalOpen, setIsModalOpen] = useState(false);
  const [searchQuery, setSearchQuery] = useState('');
  const queryClient = useQueryClient();
  const { addToast } = useToast();
  const { vendorFilters, setVendorFilters } = useFilterStore();

  const { data: vendors = [], isLoading } = useQuery({
    queryKey: ['vendors', vendorFilters],
    queryFn: () => vendorsApi.getAll(vendorFilters),
  });

  const deleteMutation = useMutation({
    mutationFn: vendorsApi.delete,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['vendors'] });
      addToast('success', 'Vendor deleted successfully');
    },
    onError: (error: Error) => {
      addToast('error', error.message);
    },
  });

  const handleDelete = (id: number) => {
    if (confirm('Are you sure you want to delete this vendor?')) {
      deleteMutation.mutate(id);
    }
  };

  const filteredVendors = vendors.filter(
    (v) =>
      v.business_name.toLowerCase().includes(searchQuery.toLowerCase()) ||
      v.contact_name?.toLowerCase().includes(searchQuery.toLowerCase())
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
        <h1 className="text-2xl font-bold text-gray-900">Vendors</h1>
        <button
          onClick={() => {
            setEditingVendor(null);
            setIsModalOpen(true);
          }}
          className="btn btn-primary"
        >
          <Plus className="w-4 h-4" />
          Add Vendor
        </button>
      </div>

      {/* Filters */}
      <div className="pf-card p-4">
        <div className="flex flex-wrap gap-4">
          <div className="relative flex-1 min-w-[200px]">
            <Search className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" />
            <input
              type="text"
              placeholder="Search vendors..."
              className="input pl-10"
              value={searchQuery}
              onChange={(e) => setSearchQuery(e.target.value)}
            />
          </div>
          <select
            className="input w-40"
            value={vendorFilters.vendor_type || ''}
            onChange={(e) => setVendorFilters({ vendor_type: e.target.value || undefined })}
          >
            <option value="">All Types</option>
            <option value="food">Food</option>
            <option value="merchandise">Merchandise</option>
            <option value="service">Service</option>
            <option value="sponsor">Sponsor</option>
            <option value="other">Other</option>
          </select>
          <select
            className="input w-40"
            value={vendorFilters.status || ''}
            onChange={(e) => setVendorFilters({ status: e.target.value || undefined })}
          >
            <option value="">All Status</option>
            <option value="applied">Applied</option>
            <option value="approved">Approved</option>
            <option value="active">Active</option>
            <option value="declined">Declined</option>
            <option value="cancelled">Cancelled</option>
          </select>
        </div>
      </div>

      {/* Vendors Grid */}
      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        {filteredVendors.length === 0 ? (
          <div className="col-span-full text-center py-12 text-gray-500">
            No vendors found.
          </div>
        ) : (
          filteredVendors.map((vendor) => (
            <div key={vendor.id} className="pf-card overflow-hidden">
              <div className="p-6">
                <div className="flex items-start justify-between mb-3">
                  <div className="flex items-center gap-2">
                    <Store className="w-5 h-5 text-gray-400" />
                    <div>
                      <h3 className="font-semibold text-gray-900">{vendor.business_name}</h3>
                      <span className="text-xs text-gray-500">
                        {vendorTypeLabels[vendor.vendor_type]}
                      </span>
                    </div>
                  </div>
                  <span className={`badge ${statusColors[vendor.status]}`}>{vendor.status}</span>
                </div>

                {vendor.contact_name && (
                  <p className="text-sm text-gray-500 mb-2">Contact: {vendor.contact_name}</p>
                )}
                {vendor.description && (
                  <p className="text-sm text-gray-500 mb-3 line-clamp-2">{vendor.description}</p>
                )}

                <div className="flex flex-wrap gap-3 text-sm">
                  {vendor.booth_fee && (
                    <div className="flex items-center gap-1 text-gray-500">
                      <DollarSign className="w-4 h-4" />
                      ${vendor.booth_fee}
                      {vendor.fee_paid ? (
                        <Check className="w-4 h-4 text-green-500" />
                      ) : (
                        <X className="w-4 h-4 text-red-500" />
                      )}
                    </div>
                  )}
                </div>

                <div className="flex flex-wrap gap-2 mt-3">
                  {vendor.insurance_verified && (
                    <span className="badge badge-green text-xs">Insurance ✓</span>
                  )}
                  {vendor.license_verified && (
                    <span className="badge badge-green text-xs">License ✓</span>
                  )}
                  {vendor.electricity_needed && (
                    <span className="badge badge-blue text-xs">Needs Power</span>
                  )}
                </div>

                <div className="flex items-center gap-2 mt-4 pt-4 border-t">
                  <button
                    onClick={() => {
                      setEditingVendor(vendor);
                      setIsModalOpen(true);
                    }}
                    className="btn btn-ghost text-sm"
                  >
                    <Edit2 className="w-4 h-4" />
                    Edit
                  </button>
                  <button
                    onClick={() => handleDelete(vendor.id)}
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

      {/* Modal placeholder */}
      {isModalOpen && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50">
          <div className="bg-white rounded-xl p-6 w-full max-w-lg max-h-[90vh] overflow-y-auto">
            <h2 className="text-lg font-semibold mb-4">
              {editingVendor ? 'Edit Vendor' : 'New Vendor'}
            </h2>
            <p className="text-gray-500">Vendor form will go here</p>
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
