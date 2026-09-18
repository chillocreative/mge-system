import { useState } from 'react';
import ContractParticularsPanel from './ContractParticularsPanel';
import PartiesPanel from './PartiesPanel';
import OrgChartPanel from './OrgChartPanel';
import ProgressPanel from './ProgressPanel';
import WorkProgrammePanel from './WorkProgrammePanel';
import RegistersPanel from './RegistersPanel';
import ResourceCategoriesPanel from './ResourceCategoriesPanel';
import ReportImagesPanel from './ReportImagesPanel';

const PANELS = [
    { id: 'particulars', label: 'Contract Particulars', component: ContractParticularsPanel },
    { id: 'parties', label: 'Parties & Contacts', component: PartiesPanel },
    { id: 'org-chart', label: 'Organisation Chart', component: OrgChartPanel },
    { id: 'progress', label: 'Progress & Baseline', component: ProgressPanel },
    { id: 'programme', label: 'Work Programme', component: WorkProgrammePanel },
    { id: 'registers', label: 'Delay Notices & Tests', component: RegistersPanel },
    { id: 'categories', label: 'Site-Log Categories', component: ResourceCategoriesPanel },
    { id: 'images', label: 'Report Images', component: ReportImagesPanel },
];

export default function ReportDataTab({ project, canEdit, initialPanel, onPanelChange }) {
    const [active, setActive] = useState(
        PANELS.some((p) => p.id === initialPanel) ? initialPanel : PANELS[0].id
    );
    const Panel = PANELS.find((p) => p.id === active)?.component;

    const handlePanelClick = (panelId) => {
        setActive(panelId);
        onPanelChange?.(panelId);
    };

    return (
        <div className="grid gap-6 lg:grid-cols-[220px_1fr]">
            <nav className="rounded-xl bg-white p-2 shadow-sm ring-1 ring-gray-200">
                <p className="px-3 py-2 text-xs font-semibold uppercase text-gray-500">Report Data</p>
                {PANELS.map((p) => (
                    <button
                        key={p.id}
                        type="button"
                        onClick={() => handlePanelClick(p.id)}
                        className={`block w-full rounded-lg px-3 py-2 text-left text-sm ${active === p.id ? 'bg-primary-50 font-medium text-primary-700' : 'text-gray-700 hover:bg-gray-50'}`}
                    >
                        {p.label}
                    </button>
                ))}
            </nav>
            <div>{Panel && <Panel project={project} canEdit={canEdit} />}</div>
        </div>
    );
}
