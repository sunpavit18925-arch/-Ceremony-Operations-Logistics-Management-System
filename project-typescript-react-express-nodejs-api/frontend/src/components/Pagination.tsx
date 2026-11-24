"use client";

import type { PaginationInfo } from "@/hooks/usePagedResource";

export default function Pagination({ info, onPageChange, loading = false }: {
  info: PaginationInfo; onPageChange: (page: number) => void; loading?: boolean;
}) {
  return (
    <nav aria-label="แบ่งหน้ารายการ" className="d-flex align-items-center justify-content-between flex-wrap gap-2 p-3 border-top">
      <span className="small text-muted" aria-live="polite">ทั้งหมด {info.total} รายการ · หน้า {info.page} / {info.totalPages}</span>
      <div className="d-flex gap-2">
        <button type="button" className="btn btn-sm btn-outline-primary" disabled={loading || info.page <= 1} onClick={() => onPageChange(info.page - 1)}>ก่อนหน้า</button>
        <button type="button" className="btn btn-sm btn-outline-primary" disabled={loading || info.page >= info.totalPages} onClick={() => onPageChange(info.page + 1)}>ถัดไป</button>
      </div>
    </nav>
  );
}
