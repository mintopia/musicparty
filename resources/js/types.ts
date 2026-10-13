export interface AuthUser {
    id: number;
    nickname: string;
    avatar: string;
    roles: string[];
    impersonating: boolean;
}

export interface PartyMembership {
    code: string;
    name: string;
    canManage: boolean;
}

export interface SharedProps {
    auth: {user: AuthUser | null; parties: PartyMembership[]};
    flash: {success: string | null; error: string | null; status: string | null};
    reverb: {appKey: string | null};
    instanceName: string | null;
    errors: Record<string, string>;
    [key: string]: unknown;
}
