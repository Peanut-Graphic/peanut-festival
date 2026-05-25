import { useState, useRef, useEffect, useCallback } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import {
  Image,
  Plus,
  Trash2,
  Edit3,
  Eye,
  Download,
  Upload,
  X,
  Save,
  RotateCcw,
  ZoomIn,
  Move,
  Type,
} from 'lucide-react';
import { client } from '../api/client';
import { useToast } from '../components/common/Toast';
import type { FlyerTemplate, FlyerUsageLog, FlyerFrame, FlyerNameBox } from '../types';

const defaultFrame: FlyerFrame = { x: 220, y: 220, w: 640, h: 640 };
const defaultNameBox: FlyerNameBox = {
  x: 540,
  y: 940,
  w: 900,
  size: 84,
  color: '#ffffff',
  stroke: '#000000',
  stroke_w: 8,
  align: 'center',
};

export function FlyerGenerator() {
  const [activeTab, setActiveTab] = useState<'templates' | 'usage'>('templates');
  const [editingTemplate, setEditingTemplate] = useState<FlyerTemplate | null>(null);
  const [isCreating, setIsCreating] = useState(false);
  const [previewTemplate, setPreviewTemplate] = useState<FlyerTemplate | null>(null);

  const { addToast } = useToast();
  const queryClient = useQueryClient();

  // Fetch templates
  const { data: templates = [], isLoading: templatesLoading } = useQuery({
    queryKey: ['flyer-templates'],
    queryFn: async () => {
      const res = await client.get<{ data: FlyerTemplate[] }>('/flyer-templates');
      return res.data.data;
    },
  });

  // Fetch usage log
  const { data: usageLog = [], isLoading: usageLoading } = useQuery({
    queryKey: ['flyer-usage'],
    queryFn: async () => {
      const res = await client.get<{ data: FlyerUsageLog[] }>('/flyer-usage');
      return res.data.data;
    },
    enabled: activeTab === 'usage',
  });

  // Mutations
  const createMutation = useMutation({
    mutationFn: (data: Partial<FlyerTemplate>) =>
      client.post('/flyer-templates', data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['flyer-templates'] });
      setIsCreating(false);
      addToast('success', 'Template created successfully');
    },
    onError: () => addToast('error', 'Failed to create template'),
  });

  const updateMutation = useMutation({
    mutationFn: ({ id, ...data }: Partial<FlyerTemplate> & { id: number }) =>
      client.put(`/flyer-templates/${id}`, data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['flyer-templates'] });
      setEditingTemplate(null);
      addToast('success', 'Template updated successfully');
    },
    onError: () => addToast('error', 'Failed to update template'),
  });

  const deleteMutation = useMutation({
    mutationFn: (id: number) => client.delete(`/flyer-templates/${id}`),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['flyer-templates'] });
      addToast('success', 'Template deleted successfully');
    },
    onError: () => addToast('error', 'Failed to delete template'),
  });

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-bold text-gray-900">Flyer Generator</h1>
        {activeTab === 'templates' && (
          <button
            onClick={() => setIsCreating(true)}
            className="inline-flex items-center gap-2 px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700"
          >
            <Plus className="w-5 h-5" />
            Add Template
          </button>
        )}
      </div>

      {/* Tabs */}
      <div className="border-b border-gray-200">
        <nav className="-mb-px flex gap-6">
          <button
            onClick={() => setActiveTab('templates')}
            className={`py-3 px-1 border-b-2 font-medium text-sm ${
              activeTab === 'templates'
                ? 'border-primary-500 text-primary-600'
                : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
            }`}
          >
            Templates ({templates.length})
          </button>
          <button
            onClick={() => setActiveTab('usage')}
            className={`py-3 px-1 border-b-2 font-medium text-sm ${
              activeTab === 'usage'
                ? 'border-primary-500 text-primary-600'
                : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
            }`}
          >
            Usage Log
          </button>
        </nav>
      </div>

      {/* Templates Tab */}
      {activeTab === 'templates' && (
        <div className="space-y-4">
          {templatesLoading ? (
            <div className="text-center py-12 text-gray-500">Loading templates...</div>
          ) : templates.length === 0 ? (
            <div className="text-center py-12 bg-white rounded-xl border border-gray-200">
              <Image className="w-12 h-12 mx-auto text-gray-400 mb-4" />
              <p className="text-gray-600">No flyer templates yet.</p>
              <p className="text-sm text-gray-500 mt-1">
                Create a template to let performers generate custom flyers.
              </p>
            </div>
          ) : (
            <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
              {templates.map((template) => (
                <TemplateCard
                  key={template.id}
                  template={template}
                  onEdit={() => setEditingTemplate(template)}
                  onDelete={() => {
                    if (confirm('Delete this template?')) {
                      deleteMutation.mutate(template.id);
                    }
                  }}
                  onPreview={() => setPreviewTemplate(template)}
                />
              ))}
            </div>
          )}
        </div>
      )}

      {/* Usage Log Tab */}
      {activeTab === 'usage' && (
        <div className="bg-white rounded-xl border border-gray-200 overflow-hidden">
          {usageLoading ? (
            <div className="text-center py-12 text-gray-500">Loading usage log...</div>
          ) : usageLog.length === 0 ? (
            <div className="text-center py-12 text-gray-500">No usage recorded yet.</div>
          ) : (
            <div className="overflow-x-auto">
              <table className="min-w-full divide-y divide-gray-200">
                <thead className="bg-gray-50">
                  <tr>
                    <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                      Preview
                    </th>
                    <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                      Date
                    </th>
                    <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                      Template
                    </th>
                    <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                      Name
                    </th>
                    <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                      Page
                    </th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-gray-200">
                  {usageLog.map((log) => (
                    <tr key={log.id}>
                      <td className="px-4 py-3">
                        {log.thumb_url ? (
                          <a
                            href={log.image_url || log.thumb_url}
                            target="_blank"
                            rel="noopener noreferrer"
                          >
                            <img
                              src={log.thumb_url}
                              alt="Flyer preview"
                              className="w-20 h-20 object-cover rounded border"
                            />
                          </a>
                        ) : (
                          <div className="w-20 h-20 bg-gray-100 rounded flex items-center justify-center">
                            <Image className="w-6 h-6 text-gray-400" />
                          </div>
                        )}
                      </td>
                      <td className="px-4 py-3 text-sm text-gray-600">
                        {new Date(log.created_at).toLocaleDateString()}
                      </td>
                      <td className="px-4 py-3 text-sm text-gray-900">
                        {log.template_name || '-'}
                      </td>
                      <td className="px-4 py-3 text-sm text-gray-900">
                        {log.performer_name || '-'}
                      </td>
                      <td className="px-4 py-3 text-sm text-gray-500 max-w-xs truncate">
                        {log.page_url ? (
                          <a
                            href={log.page_url}
                            target="_blank"
                            rel="noopener noreferrer"
                            className="text-primary-600 hover:underline"
                          >
                            {new URL(log.page_url).pathname}
                          </a>
                        ) : (
                          '-'
                        )}
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </div>
      )}

      {/* Template Editor Modal */}
      {(isCreating || editingTemplate) && (
        <TemplateEditorModal
          template={editingTemplate}
          onClose={() => {
            setIsCreating(false);
            setEditingTemplate(null);
          }}
          onSave={(data) => {
            if (editingTemplate) {
              updateMutation.mutate({ id: editingTemplate.id, ...data });
            } else {
              createMutation.mutate(data);
            }
          }}
          isSaving={createMutation.isPending || updateMutation.isPending}
        />
      )}

      {/* Preview Modal */}
      {previewTemplate && (
        <FlyerPreviewModal
          template={previewTemplate}
          onClose={() => setPreviewTemplate(null)}
        />
      )}
    </div>
  );
}

// Template Card Component
function TemplateCard({
  template,
  onEdit,
  onDelete,
  onPreview,
}: {
  template: FlyerTemplate;
  onEdit: () => void;
  onDelete: () => void;
  onPreview: () => void;
}) {
  return (
    <div className="bg-white rounded-xl border border-gray-200 overflow-hidden">
      <div className="aspect-square bg-gray-100 relative">
        {template.template_url ? (
          <img
            src={template.template_url}
            alt={template.name}
            className="w-full h-full object-cover"
          />
        ) : (
          <div className="w-full h-full flex items-center justify-center">
            <Image className="w-12 h-12 text-gray-400" />
          </div>
        )}
        <div className="absolute top-2 right-2 flex gap-1">
          {template.is_active ? (
            <span className="px-2 py-1 bg-green-100 text-green-700 text-xs font-medium rounded">
              Active
            </span>
          ) : (
            <span className="px-2 py-1 bg-gray-100 text-gray-600 text-xs font-medium rounded">
              Inactive
            </span>
          )}
        </div>
      </div>
      <div className="p-4">
        <h3 className="font-semibold text-gray-900">{template.name}</h3>
        {template.title && <p className="text-sm text-gray-600 mt-1">{template.title}</p>}
        {template.subtitle && (
          <p className="text-xs text-gray-500">{template.subtitle}</p>
        )}
        <div className="flex gap-2 mt-4">
          <button
            onClick={onPreview}
            className="flex-1 inline-flex items-center justify-center gap-1 px-3 py-2 text-sm border border-gray-300 rounded-lg hover:bg-gray-50"
          >
            <Eye className="w-4 h-4" />
            Preview
          </button>
          <button
            onClick={onEdit}
            className="flex-1 inline-flex items-center justify-center gap-1 px-3 py-2 text-sm border border-gray-300 rounded-lg hover:bg-gray-50"
          >
            <Edit3 className="w-4 h-4" />
            Edit
          </button>
          <button
            onClick={onDelete}
            className="p-2 text-red-600 hover:bg-red-50 rounded-lg"
          >
            <Trash2 className="w-4 h-4" />
          </button>
        </div>
      </div>
    </div>
  );
}

// Template Editor Modal
function TemplateEditorModal({
  template,
  onClose,
  onSave,
  isSaving,
}: {
  template: FlyerTemplate | null;
  onClose: () => void;
  onSave: (data: Partial<FlyerTemplate>) => void;
  isSaving: boolean;
}) {
  const [formData, setFormData] = useState({
    name: template?.name || '',
    template_url: template?.template_url || '',
    mask_url: template?.mask_url || '',
    title: template?.title || '',
    subtitle: template?.subtitle || '',
    is_active: template?.is_active ?? true,
    frame: template?.frame || { ...defaultFrame },
    namebox: template?.namebox || { ...defaultNameBox },
  });

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    if (!formData.name.trim()) return;
    onSave(formData);
  };

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50">
      <div className="bg-white rounded-xl shadow-xl max-w-4xl w-full mx-4 max-h-[90vh] overflow-y-auto">
        <form onSubmit={handleSubmit}>
          <div className="flex items-center justify-between p-4 border-b">
            <h2 className="text-lg font-semibold">
              {template ? 'Edit Template' : 'New Template'}
            </h2>
            <button type="button" onClick={onClose} className="p-2 hover:bg-gray-100 rounded-lg">
              <X className="w-5 h-5" />
            </button>
          </div>

          <div className="p-6 space-y-6">
            {/* Basic Info */}
            <div className="grid gap-4 md:grid-cols-2">
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1" htmlFor="flyergenerator-template-name">
                  Template Name *
                </label>
                <input id="flyergenerator-template-name"
                  type="text"
                  value={formData.name}
                  onChange={(e) => setFormData({ ...formData, name: e.target.value })}
                  className="w-full px-3 py-2 text-gray-900 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                  required
                />
              </div>
              <div className="flex items-center gap-2">
                <input
                  type="checkbox"
                  id="is_active"
                  checked={formData.is_active}
                  onChange={(e) => setFormData({ ...formData, is_active: e.target.checked })}
                  className="w-4 h-4 text-primary-600 rounded"
                />
                <label htmlFor="is_active" className="text-sm text-gray-700">
                  Active (visible in shortcode)
                </label>
              </div>
            </div>

            {/* Image URLs */}
            <div className="grid gap-4 md:grid-cols-2">
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1" htmlFor="flyergenerator-template-image-url">
                  Template Image URL *
                </label>
                <input id="flyergenerator-template-image-url"
                  type="url"
                  value={formData.template_url}
                  onChange={(e) => setFormData({ ...formData, template_url: e.target.value })}
                  className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                  placeholder="https://..."
                />
                <p className="text-xs text-gray-500 mt-1">
                  The overlay/background image for the flyer
                </p>
              </div>
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1" htmlFor="flyergenerator-mask-image-url">
                  Mask Image URL
                </label>
                <input id="flyergenerator-mask-image-url"
                  type="url"
                  value={formData.mask_url}
                  onChange={(e) => setFormData({ ...formData, mask_url: e.target.value })}
                  className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                  placeholder="https://..."
                />
                <p className="text-xs text-gray-500 mt-1">
                  Optional mask for photo compositing
                </p>
              </div>
            </div>

            {/* Title/Subtitle */}
            <div className="grid gap-4 md:grid-cols-2">
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1" htmlFor="flyergenerator-title">Title</label>
                <input id="flyergenerator-title"
                  type="text"
                  value={formData.title}
                  onChange={(e) => setFormData({ ...formData, title: e.target.value })}
                  className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                  placeholder="e.g., Comedy Festival 2025"
                />
              </div>
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1" htmlFor="flyergenerator-subtitle">Subtitle</label>
                <input id="flyergenerator-subtitle"
                  type="text"
                  value={formData.subtitle}
                  onChange={(e) => setFormData({ ...formData, subtitle: e.target.value })}
                  className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                  placeholder="e.g., Official Selection"
                />
              </div>
            </div>

            {/* Frame Settings */}
            <div>
              <h3 className="text-sm font-medium text-gray-700 mb-2">
                Photo Frame Position (x, y, width, height)
              </h3>
              <div className="grid grid-cols-4 gap-4">
                <div>
                  <label className="block text-xs text-gray-500 mb-1" htmlFor="flyergenerator-x">X</label>
                  <input id="flyergenerator-x"
                    type="number"
                    value={formData.frame.x}
                    onChange={(e) =>
                      setFormData({
                        ...formData,
                        frame: { ...formData.frame, x: parseInt(e.target.value) || 0 },
                      })
                    }
                    className="w-full px-3 py-2 border border-gray-300 rounded-lg"
                  />
                </div>
                <div>
                  <label className="block text-xs text-gray-500 mb-1" htmlFor="flyergenerator-y">Y</label>
                  <input id="flyergenerator-y"
                    type="number"
                    value={formData.frame.y}
                    onChange={(e) =>
                      setFormData({
                        ...formData,
                        frame: { ...formData.frame, y: parseInt(e.target.value) || 0 },
                      })
                    }
                    className="w-full px-3 py-2 border border-gray-300 rounded-lg"
                  />
                </div>
                <div>
                  <label className="block text-xs text-gray-500 mb-1" htmlFor="flyergenerator-width">Width</label>
                  <input id="flyergenerator-width"
                    type="number"
                    value={formData.frame.w}
                    onChange={(e) =>
                      setFormData({
                        ...formData,
                        frame: { ...formData.frame, w: parseInt(e.target.value) || 0 },
                      })
                    }
                    className="w-full px-3 py-2 border border-gray-300 rounded-lg"
                  />
                </div>
                <div>
                  <label className="block text-xs text-gray-500 mb-1" htmlFor="flyergenerator-height">Height</label>
                  <input id="flyergenerator-height"
                    type="number"
                    value={formData.frame.h}
                    onChange={(e) =>
                      setFormData({
                        ...formData,
                        frame: { ...formData.frame, h: parseInt(e.target.value) || 0 },
                      })
                    }
                    className="w-full px-3 py-2 border border-gray-300 rounded-lg"
                  />
                </div>
              </div>
            </div>

            {/* Name Box Settings */}
            <div>
              <h3 className="text-sm font-medium text-gray-700 mb-2">Name Text Overlay</h3>
              <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
                <div>
                  <label className="block text-xs text-gray-500 mb-1" htmlFor="flyergenerator-x-position">X Position</label>
                  <input id="flyergenerator-x-position"
                    type="number"
                    value={formData.namebox.x}
                    onChange={(e) =>
                      setFormData({
                        ...formData,
                        namebox: { ...formData.namebox, x: parseInt(e.target.value) || 0 },
                      })
                    }
                    className="w-full px-3 py-2 border border-gray-300 rounded-lg"
                  />
                </div>
                <div>
                  <label className="block text-xs text-gray-500 mb-1" htmlFor="flyergenerator-y-position">Y Position</label>
                  <input id="flyergenerator-y-position"
                    type="number"
                    value={formData.namebox.y}
                    onChange={(e) =>
                      setFormData({
                        ...formData,
                        namebox: { ...formData.namebox, y: parseInt(e.target.value) || 0 },
                      })
                    }
                    className="w-full px-3 py-2 border border-gray-300 rounded-lg"
                  />
                </div>
                <div>
                  <label className="block text-xs text-gray-500 mb-1" htmlFor="flyergenerator-max-width">Max Width</label>
                  <input id="flyergenerator-max-width"
                    type="number"
                    value={formData.namebox.w}
                    onChange={(e) =>
                      setFormData({
                        ...formData,
                        namebox: { ...formData.namebox, w: parseInt(e.target.value) || 0 },
                      })
                    }
                    className="w-full px-3 py-2 border border-gray-300 rounded-lg"
                  />
                </div>
                <div>
                  <label className="block text-xs text-gray-500 mb-1" htmlFor="flyergenerator-font-size">Font Size</label>
                  <input id="flyergenerator-font-size"
                    type="number"
                    value={formData.namebox.size}
                    onChange={(e) =>
                      setFormData({
                        ...formData,
                        namebox: { ...formData.namebox, size: parseInt(e.target.value) || 72 },
                      })
                    }
                    className="w-full px-3 py-2 border border-gray-300 rounded-lg"
                  />
                </div>
                <div>
                  <label className="block text-xs text-gray-500 mb-1" htmlFor="flyergenerator-text-color">Text Color</label>
                  <input id="flyergenerator-text-color"
                    type="color"
                    value={formData.namebox.color}
                    onChange={(e) =>
                      setFormData({
                        ...formData,
                        namebox: { ...formData.namebox, color: e.target.value },
                      })
                    }
                    className="w-full h-10 border border-gray-300 rounded-lg cursor-pointer"
                  />
                </div>
                <div>
                  <label className="block text-xs text-gray-500 mb-1" htmlFor="flyergenerator-stroke-color">Stroke Color</label>
                  <input id="flyergenerator-stroke-color"
                    type="color"
                    value={formData.namebox.stroke}
                    onChange={(e) =>
                      setFormData({
                        ...formData,
                        namebox: { ...formData.namebox, stroke: e.target.value },
                      })
                    }
                    className="w-full h-10 border border-gray-300 rounded-lg cursor-pointer"
                  />
                </div>
                <div>
                  <label className="block text-xs text-gray-500 mb-1" htmlFor="flyergenerator-stroke-width">Stroke Width</label>
                  <input id="flyergenerator-stroke-width"
                    type="number"
                    value={formData.namebox.stroke_w}
                    onChange={(e) =>
                      setFormData({
                        ...formData,
                        namebox: { ...formData.namebox, stroke_w: parseInt(e.target.value) || 0 },
                      })
                    }
                    className="w-full px-3 py-2 border border-gray-300 rounded-lg"
                  />
                </div>
                <div>
                  <label className="block text-xs text-gray-500 mb-1" htmlFor="flyergenerator-alignment">Alignment</label>
                  <select id="flyergenerator-alignment"
                    value={formData.namebox.align}
                    onChange={(e) =>
                      setFormData({
                        ...formData,
                        namebox: {
                          ...formData.namebox,
                          align: e.target.value as 'left' | 'center' | 'right',
                        },
                      })
                    }
                    className="w-full px-3 py-2 border border-gray-300 rounded-lg"
                  >
                    <option value="left">Left</option>
                    <option value="center">Center</option>
                    <option value="right">Right</option>
                  </select>
                </div>
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
              disabled={isSaving || !formData.name.trim()}
              className="inline-flex items-center gap-2 px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700 disabled:opacity-50"
            >
              <Save className="w-4 h-4" />
              {isSaving ? 'Saving...' : 'Save Template'}
            </button>
          </div>
        </form>
      </div>
    </div>
  );
}

// Flyer Preview Modal with Canvas Editor
function FlyerPreviewModal({
  template,
  onClose,
}: {
  template: FlyerTemplate;
  onClose: () => void;
}) {
  const canvasRef = useRef<HTMLCanvasElement>(null);
  const [headImg, setHeadImg] = useState<HTMLImageElement | null>(null);
  const [templateImg, setTemplateImg] = useState<HTMLImageElement | null>(null);
  const [maskImg, setMaskImg] = useState<HTMLImageElement | null>(null);

  const [zoom, setZoom] = useState(1);
  const [rotation, setRotation] = useState(0);
  const [offsetX, setOffsetX] = useState(0);
  const [offsetY, setOffsetY] = useState(0);
  const [name, setName] = useState('Your Name');

  // Load template and mask images
  useEffect(() => {
    if (template.template_url) {
      const img = new window.Image();
      img.crossOrigin = 'anonymous';
      img.onload = () => setTemplateImg(img);
      img.src = template.template_url;
    }

    if (template.mask_url) {
      const img = new window.Image();
      img.crossOrigin = 'anonymous';
      img.onload = () => setMaskImg(img);
      img.src = template.mask_url;
    }
  }, [template]);

  // Render canvas
  const render = useCallback(() => {
    const canvas = canvasRef.current;
    if (!canvas || !templateImg) return;

    const ctx = canvas.getContext('2d');
    if (!ctx) return;

    canvas.width = templateImg.width;
    canvas.height = templateImg.height;

    ctx.clearRect(0, 0, canvas.width, canvas.height);
    ctx.drawImage(templateImg, 0, 0, canvas.width, canvas.height);

    if (headImg) {
      // Create temporary canvas for compositing
      const temp = document.createElement('canvas');
      const tctx = temp.getContext('2d');
      if (!tctx) return;

      temp.width = canvas.width;
      temp.height = canvas.height;

      const baseScale = Math.max(canvas.width / headImg.width, canvas.height / headImg.height);
      const drawW = headImg.width * baseScale * zoom;
      const drawH = headImg.height * baseScale * zoom;

      tctx.save();
      tctx.translate(canvas.width / 2, canvas.height / 2);
      tctx.rotate((rotation * Math.PI) / 180);
      const dx = -drawW / 2 + offsetX;
      const dy = -drawH / 2 + offsetY;
      tctx.drawImage(headImg, dx, dy, drawW, drawH);
      tctx.restore();

      if (maskImg) {
        tctx.globalCompositeOperation = 'destination-in';
        tctx.drawImage(maskImg, 0, 0, canvas.width, canvas.height);
        tctx.globalCompositeOperation = 'source-over';
      }

      ctx.drawImage(temp, 0, 0);
    }

    // Draw name text
    if (name.trim()) {
      const nb = template.namebox;
      const cx = nb.x || canvas.width / 2;
      const cy = nb.y || canvas.height - 110;
      const maxW = nb.w || Math.round(canvas.width * 0.8);
      let size = Math.max(12, nb.size || 72);

      ctx.save();
      ctx.textBaseline = 'middle';
      ctx.textAlign = nb.align || 'center';

      // Fit to width
      do {
        ctx.font = `900 ${size}px Arial, Helvetica, sans-serif`;
        if (ctx.measureText(name).width <= maxW) break;
        size -= 2;
      } while (size > 12);

      ctx.translate(cx, cy);

      const sw = Math.max(0, nb.stroke_w || 8);
      if (sw > 0) {
        ctx.lineWidth = sw;
        ctx.strokeStyle = nb.stroke || '#000';
        ctx.strokeText(name, 0, 0);
      }
      ctx.fillStyle = nb.color || '#fff';
      ctx.fillText(name, 0, 0);
      ctx.restore();
    }
  }, [templateImg, headImg, maskImg, zoom, rotation, offsetX, offsetY, name, template.namebox]);

  useEffect(() => {
    render();
  }, [render]);

  // Handle file upload
  const handleFileChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0];
    if (!file) return;

    const img = new window.Image();
    img.onload = () => {
      setHeadImg(img);
      URL.revokeObjectURL(img.src);
    };
    img.src = URL.createObjectURL(file);
  };

  // Handle download
  const handleDownload = () => {
    const canvas = canvasRef.current;
    if (!canvas) return;

    render();
    const dataURL = canvas.toDataURL('image/jpeg', 0.92);
    const link = document.createElement('a');
    link.download = `${template.name.replace(/\s+/g, '-')}-${name.replace(/\s+/g, '-')}.jpg`;
    link.href = dataURL;
    link.click();
  };

  // Handle reset
  const handleReset = () => {
    setZoom(1);
    setRotation(0);
    setOffsetX(0);
    setOffsetY(0);
    setHeadImg(null);
  };

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50">
      <div className="bg-white rounded-xl shadow-xl max-w-6xl w-full mx-4 max-h-[95vh] overflow-y-auto">
        <div className="flex items-center justify-between p-4 border-b">
          <h2 className="text-lg font-semibold">Preview: {template.name}</h2>
          <button onClick={onClose} className="p-2 hover:bg-gray-100 rounded-lg">
            <X className="w-5 h-5" />
          </button>
        </div>

        <div className="p-6 grid gap-6 lg:grid-cols-[1fr_300px]">
          {/* Canvas */}
          <div className="bg-gray-100 rounded-lg p-4 flex items-center justify-center">
            <canvas
              ref={canvasRef}
              className="max-w-full max-h-[60vh] border border-gray-300 rounded-lg"
            />
          </div>

          {/* Controls */}
          <div className="space-y-6">
            {/* Upload */}
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-2">
                <Upload className="w-4 h-4 inline mr-1" />
                Upload Headshot
              </label>
              <input
                type="file"
                accept="image/*"
                onChange={handleFileChange}
                className="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm"
              />
            </div>

            {/* Photo Controls */}
            {headImg && (
              <>
                <div>
                  <label className="flex items-center gap-2 text-sm font-medium text-gray-700 mb-2">
                    <ZoomIn className="w-4 h-4" />
                    Zoom
                  </label>
                  <input
                    type="range"
                    min="0.2"
                    max="3"
                    step="0.01"
                    value={zoom}
                    onChange={(e) => setZoom(parseFloat(e.target.value))}
                    className="w-full"
                  />
                </div>

                <div>
                  <label className="flex items-center gap-2 text-sm font-medium text-gray-700 mb-2">
                    <RotateCcw className="w-4 h-4" />
                    Rotation
                  </label>
                  <input
                    type="range"
                    min="-30"
                    max="30"
                    step="0.5"
                    value={rotation}
                    onChange={(e) => setRotation(parseFloat(e.target.value))}
                    className="w-full"
                  />
                </div>

                <div>
                  <label className="flex items-center gap-2 text-sm font-medium text-gray-700 mb-2">
                    <Move className="w-4 h-4" />
                    Position X
                  </label>
                  <input
                    type="range"
                    min="-800"
                    max="800"
                    step="1"
                    value={offsetX}
                    onChange={(e) => setOffsetX(parseInt(e.target.value))}
                    className="w-full"
                  />
                </div>

                <div>
                  <label className="flex items-center gap-2 text-sm font-medium text-gray-700 mb-2">
                    <Move className="w-4 h-4" />
                    Position Y
                  </label>
                  <input
                    type="range"
                    min="-800"
                    max="800"
                    step="1"
                    value={offsetY}
                    onChange={(e) => setOffsetY(parseInt(e.target.value))}
                    className="w-full"
                  />
                </div>
              </>
            )}

            {/* Name Input */}
            <div>
              <label className="flex items-center gap-2 text-sm font-medium text-gray-700 mb-2">
                <Type className="w-4 h-4" />
                Name
              </label>
              <input
                type="text"
                value={name}
                onChange={(e) => setName(e.target.value)}
                className="w-full px-3 py-2 border border-gray-300 rounded-lg"
                placeholder="Enter name"
              />
            </div>

            {/* Actions */}
            <div className="flex gap-2">
              <button
                onClick={handleReset}
                className="flex-1 inline-flex items-center justify-center gap-2 px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50"
              >
                <RotateCcw className="w-4 h-4" />
                Reset
              </button>
              <button
                onClick={handleDownload}
                className="flex-1 inline-flex items-center justify-center gap-2 px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700"
              >
                <Download className="w-4 h-4" />
                Download
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}
