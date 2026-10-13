const base = (code: string): string => `/parties/${code}/flagged`;

export const flaggedUrl = (code: string): string => base(code);
export const flaggedApproveUrl = (code: string, id: number): string => `${base(code)}/${id}/approve`;
export const flaggedRejectUrl = (code: string, id: number): string => `${base(code)}/${id}/reject`;
export const flaggedWithdrawUrl = (code: string, id: number): string => `/parties/${code}/requests/${id}/flag`;
