import AttachedPagesPanel from './AttachedPagesPanel';
import ATTACHED_PAGE_KINDS from './attachedPages';

// 2.5 (Gantt chart pages) editor: thin wrapper around AttachedPagesPanel
// pinned to the `gantt_page` asset kind, kept so the 2.5 host doesn't need
// to know about the generic panel's `kind`/`title`/`hint` props.
export default function GanttAssetsPanel({ reportId, canEdit, onUploaded }) {
    return (
        <AttachedPagesPanel
            reportId={reportId}
            canEdit={canEdit}
            kind={ATTACHED_PAGE_KINDS['2.5']}
            hint="No Gantt pages uploaded yet."
            onUploaded={onUploaded}
        />
    );
}
