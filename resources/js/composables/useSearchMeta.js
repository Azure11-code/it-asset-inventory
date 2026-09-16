import {
    MagnifyingGlassIcon, CpuChipIcon, UserIcon, MapPinIcon,
    BuildingOffice2Icon, Squares2X2Icon, TagIcon, ClipboardDocumentListIcon,
    ExclamationTriangleIcon, DocumentTextIcon, ArrowsRightLeftIcon,
    ShieldCheckIcon, PencilSquareIcon, UsersIcon,
} from '@heroicons/vue/24/outline';

/**
 * Icon + colour per search result type. Shared by the quick-search dropdown
 * and the full results page so a group always looks the same in both.
 * Keys match SearchController::TYPES.
 */
const ICONS = {
    asset:          CpuChipIcon,
    employee:       UserIcon,
    permit:         ClipboardDocumentListIcon,
    incident:       ExclamationTriangleIcon,
    recommendation: DocumentTextIcon,
    movement:       ArrowsRightLeftIcon,
    location:       MapPinIcon,
    department:     BuildingOffice2Icon,
    category:       Squares2X2Icon,
    brand:          TagIcon,
    condition:      ShieldCheckIcon,
    signatory:      PencilSquareIcon,
    user:           UsersIcon,
};

const TONES = {
    asset:          'bg-brand-50 text-brand-700',
    employee:       'bg-sky-50 text-sky-700',
    permit:         'bg-teal-50 text-teal-700',
    incident:       'bg-orange-50 text-orange-700',
    recommendation: 'bg-violet-50 text-violet-700',
    movement:       'bg-cyan-50 text-cyan-700',
    location:       'bg-amber-50 text-amber-700',
    department:     'bg-emerald-50 text-emerald-700',
    category:       'bg-indigo-50 text-indigo-700',
    brand:          'bg-rose-50 text-rose-700',
    condition:      'bg-lime-50 text-lime-700',
    signatory:      'bg-fuchsia-50 text-fuchsia-700',
    user:           'bg-slate-100 text-slate-700',
};

export function useSearchMeta() {
    return {
        iconFor: (type) => ICONS[type] || MagnifyingGlassIcon,
        toneFor: (type) => TONES[type] || 'bg-slate-100 text-slate-700',
    };
}
