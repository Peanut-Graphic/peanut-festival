import { useEffect } from 'react';
import { useForm } from 'react-hook-form';
import { useMutation, useQueryClient, useQuery } from '@tanstack/react-query';
import { volunteersApi, festivalsApi } from '@/api/endpoints';
import { useToast } from '@/components/common/Toast';
import {
  Modal,
  InputField,
  TextareaField,
  SelectField,
  FormRow,
  FormSection,
} from '@/components/common';
import type { Volunteer } from '@/types';

interface VolunteerFormProps {
  isOpen: boolean;
  onClose: () => void;
  volunteer?: Volunteer | null;
}

interface VolunteerFormData {
  festival_id: number | '';
  name: string;
  email: string;
  phone: string;
  emergency_contact: string;
  emergency_phone: string;
  shirt_size: string;
  dietary_restrictions: string;
  status: Volunteer['status'];
  notes: string;
  skills: string[];
}

const statusOptions = [
  { value: 'applied', label: 'Applied' },
  { value: 'approved', label: 'Approved' },
  { value: 'active', label: 'Active' },
  { value: 'inactive', label: 'Inactive' },
  { value: 'declined', label: 'Declined' },
];

const shirtSizes = [
  { value: '', label: 'Select size...' },
  { value: 'XS', label: 'XS' },
  { value: 'S', label: 'Small' },
  { value: 'M', label: 'Medium' },
  { value: 'L', label: 'Large' },
  { value: 'XL', label: 'XL' },
  { value: '2XL', label: '2XL' },
  { value: '3XL', label: '3XL' },
];

const skillOptions = [
  { value: 'hospitality', label: 'Hospitality / Customer Service' },
  { value: 'tech', label: 'Tech / AV Equipment' },
  { value: 'photography', label: 'Photography / Video' },
  { value: 'social_media', label: 'Social Media' },
  { value: 'driving', label: 'Valid Driver\'s License' },
  { value: 'setup', label: 'Setup / Breakdown' },
  { value: 'box_office', label: 'Box Office / Will Call' },
  { value: 'green_room', label: 'Green Room / Performer Support' },
];

export function VolunteerForm({ isOpen, onClose, volunteer }: VolunteerFormProps) {
  const queryClient = useQueryClient();
  const { addToast } = useToast();
  const isEditing = !!volunteer;

  const { data: festivals = [] } = useQuery({
    queryKey: ['festivals'],
    queryFn: festivalsApi.getAll,
  });

  const {
    register,
    handleSubmit,
    reset,
    watch,
    setValue,
    formState: { errors },
  } = useForm<VolunteerFormData>({
    defaultValues: {
      festival_id: '',
      name: '',
      email: '',
      phone: '',
      emergency_contact: '',
      emergency_phone: '',
      shirt_size: '',
      dietary_restrictions: '',
      status: 'applied',
      notes: '',
      skills: [],
    },
  });

  const selectedSkills = watch('skills');

  useEffect(() => {
    if (volunteer) {
      reset({
        festival_id: volunteer.festival_id || '',
        name: volunteer.name,
        email: volunteer.email,
        phone: volunteer.phone || '',
        emergency_contact: volunteer.emergency_contact || '',
        emergency_phone: volunteer.emergency_phone || '',
        shirt_size: volunteer.shirt_size || '',
        dietary_restrictions: volunteer.dietary_restrictions || '',
        status: volunteer.status,
        notes: volunteer.notes || '',
        skills: volunteer.skills || [],
      });
    } else {
      reset({
        festival_id: '',
        name: '',
        email: '',
        phone: '',
        emergency_contact: '',
        emergency_phone: '',
        shirt_size: '',
        dietary_restrictions: '',
        status: 'applied',
        notes: '',
        skills: [],
      });
    }
  }, [volunteer, reset]);

  const toggleSkill = (skill: string) => {
    const current = selectedSkills || [];
    if (current.includes(skill)) {
      setValue('skills', current.filter((s) => s !== skill));
    } else {
      setValue('skills', [...current, skill]);
    }
  };

  const createMutation = useMutation({
    mutationFn: volunteersApi.create,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['volunteers'] });
      addToast('success', 'Volunteer created successfully');
      onClose();
    },
    onError: (error: Error) => {
      addToast('error', error.message);
    },
  });

  const updateMutation = useMutation({
    mutationFn: ({ id, data }: { id: number; data: Partial<Volunteer> }) =>
      volunteersApi.update(id, data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['volunteers'] });
      addToast('success', 'Volunteer updated successfully');
      onClose();
    },
    onError: (error: Error) => {
      addToast('error', error.message);
    },
  });

  const onSubmit = (data: VolunteerFormData) => {
    const payload: Partial<Volunteer> = {
      festival_id: data.festival_id ? Number(data.festival_id) : 0,
      name: data.name,
      email: data.email,
      phone: data.phone || null,
      emergency_contact: data.emergency_contact || null,
      emergency_phone: data.emergency_phone || null,
      shirt_size: data.shirt_size || null,
      dietary_restrictions: data.dietary_restrictions || null,
      status: data.status,
      notes: data.notes || null,
      skills: data.skills,
    };

    if (isEditing && volunteer) {
      updateMutation.mutate({ id: volunteer.id, data: payload });
    } else {
      createMutation.mutate(payload);
    }
  };

  const isPending = createMutation.isPending || updateMutation.isPending;

  return (
    <Modal
      isOpen={isOpen}
      onClose={onClose}
      title={isEditing ? 'Edit Volunteer' : 'New Volunteer'}
      size="lg"
      footer={
        <>
          <button type="button" onClick={onClose} className="btn btn-secondary" disabled={isPending}>
            Cancel
          </button>
          <button type="submit" form="volunteer-form" className="btn btn-primary" disabled={isPending}>
            {isPending ? 'Saving...' : isEditing ? 'Update Volunteer' : 'Create Volunteer'}
          </button>
        </>
      }
    >
      <form id="volunteer-form" onSubmit={handleSubmit(onSubmit)} className="space-y-6">
        <FormSection title="Basic Information">
          <FormRow>
            <SelectField
              label="Festival"
              required
              options={[
                { value: '', label: 'Select festival...' },
                ...festivals.map((f) => ({ value: String(f.id), label: f.name })),
              ]}
              error={errors.festival_id?.message}
              {...register('festival_id', { required: 'Festival is required' })}
            />
            <SelectField label="Status" options={statusOptions} {...register('status')} />
          </FormRow>

          <FormRow>
            <InputField
              label="Full Name"
              required
              placeholder="John Doe"
              error={errors.name?.message}
              {...register('name', { required: 'Name is required' })}
            />
            <InputField
              label="Email"
              type="email"
              required
              placeholder="john@example.com"
              error={errors.email?.message}
              {...register('email', { required: 'Email is required' })}
            />
          </FormRow>

          <FormRow>
            <InputField label="Phone" type="tel" placeholder="555-123-4567" {...register('phone')} />
            <SelectField label="T-Shirt Size" options={shirtSizes} {...register('shirt_size')} />
          </FormRow>
        </FormSection>

        <FormSection title="Emergency Contact">
          <FormRow>
            <InputField
              label="Emergency Contact Name"
              placeholder="Jane Doe"
              {...register('emergency_contact')}
            />
            <InputField
              label="Emergency Contact Phone"
              type="tel"
              placeholder="555-987-6543"
              {...register('emergency_phone')}
            />
          </FormRow>
        </FormSection>

        <FormSection title="Skills">
          <div className="grid grid-cols-2 gap-2">
            {skillOptions.map((skill) => (
              <label
                key={skill.value}
                className={`flex items-center gap-2 p-3 rounded-lg border cursor-pointer transition-colors ${
                  selectedSkills?.includes(skill.value)
                    ? 'bg-primary-50 border-primary-300'
                    : 'bg-white border-gray-200 hover:border-gray-300'
                }`}
              >
                <input
                  type="checkbox"
                  checked={selectedSkills?.includes(skill.value) || false}
                  onChange={() => toggleSkill(skill.value)}
                  className="sr-only"
                />
                <span
                  className={`w-4 h-4 rounded-sm flex items-center justify-center text-xs ${
                    selectedSkills?.includes(skill.value)
                      ? 'bg-primary-500 text-white'
                      : 'bg-gray-200'
                  }`}
                >
                  {selectedSkills?.includes(skill.value) && '✓'}
                </span>
                <span className="text-sm">{skill.label}</span>
              </label>
            ))}
          </div>
        </FormSection>

        <FormSection title="Additional Information">
          <TextareaField
            label="Dietary Restrictions"
            placeholder="Any food allergies or dietary needs..."
            rows={2}
            {...register('dietary_restrictions')}
          />

          <TextareaField
            label="Internal Notes"
            placeholder="Notes about this volunteer..."
            rows={3}
            {...register('notes')}
          />
        </FormSection>
      </form>
    </Modal>
  );
}
