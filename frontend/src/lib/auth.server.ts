import { cookies } from 'next/headers';
import { AUTH_COOKIE } from './auth';

export async function getServerToken(): Promise<string | undefined> {
    const cookieStore = await cookies();
    return cookieStore.get(AUTH_COOKIE)?.value;
}
