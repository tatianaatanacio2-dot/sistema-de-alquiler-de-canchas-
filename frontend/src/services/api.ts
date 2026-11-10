import { reactive, ref } from 'vue';

export type Usuario = { nombre: string; correo: string };
export type Cancha = { id: number; nombre: string; tarifa: number | null; activa: number | boolean };
export type Cliente = { id: number; dni: string; nombre: string; telefono: string; correo: string };
export type Estado = 'confirmada' | 'cancelada' | 'completada';
export type Reserva = {
  id: number; cliente_id: number | null; cliente: string; inicio: string; fin: string;
  tarifa: number; total: string; estado: Estado;
};
export type Acceso = { requiereCuenta: boolean; equipoLocal: boolean };
type Credenciales = { token: string; usuario: Usuario };

export const android = import.meta.env.QUASAR_CAPACITOR_MODE;
export const servidor = ref(localStorage.getItem('canchas.servidor') || '');
export const session = reactive<{ usuario: Usuario | null; lista: boolean }>({ usuario: null, lista: false });
let token = sessionStorage.getItem('canchas.token') || '';

export function conectar(direccion: string) {
  const url = new URL(direccion.trim());
  const privada = /^(?:10\.\d+|192\.168|172\.(?:1[6-9]|2\d|3[01]))\.\d+\.\d+$/.test(url.hostname);
  if (url.username || url.password || url.pathname !== '/' || url.search || url.hash
    || !(url.protocol === 'https:' || (url.protocol === 'http:' && privada))) {
    throw new Error('Ingresa la dirección del servidor local, por ejemplo http://192.168.1.10:8012.');
  }
  if (url.origin !== servidor.value) {
    cerrarSesion();
  }
  servidor.value = url.origin;
  localStorage.setItem('canchas.servidor', url.origin);
}

export async function api<T>(ruta: string, method = 'GET', datos?: unknown): Promise<T> {
  let respuesta: Response;
  try {
    respuesta = await fetch((android ? servidor.value : '') + '/api' + ruta, {
      method,
      headers: {
        Accept: 'application/json',
        ...(datos === undefined ? {} : { 'Content-Type': 'application/json' }),
        ...(token ? { Authorization: 'Bearer ' + token } : {}),
      },
      ...(datos === undefined ? {} : { body: JSON.stringify(datos) }),
      signal: AbortSignal.timeout(12000),
    });
  } catch {
    throw new Error('No se pudo conectar con el servidor de reservas.');
  }
  const contenido = await respuesta.json().catch(() => null);
  if (!respuesta.ok || contenido === null) {
    if (respuesta.status === 401) cerrarSesion();
    const validacion = contenido?.errors && Object.values(contenido.errors).flat().join(' ');
    throw new Error(validacion || contenido?.message || 'No se pudo completar la operación.');
  }
  return contenido as T;
}

export async function ingresar(datos: { nombre: string; correo: string; clave: string }, configurar: boolean) {
  const resultado = await api<Credenciales>(configurar ? '/configurar' : '/ingresar', 'POST', datos);
  token = resultado.token;
  sessionStorage.setItem('canchas.token', token);
  session.usuario = resultado.usuario;
}

export async function cargarSesion() {
  if (token) {
    try { session.usuario = await api<Usuario>('/sesion'); }
    catch { cerrarSesion(); }
  }
  session.lista = true;
}

export function cerrarSesion() {
  token = '';
  sessionStorage.removeItem('canchas.token');
  session.usuario = null;
}

export const estados: Record<Estado, string> = {
  confirmada: 'Confirmada', completada: 'Completada', cancelada: 'Cancelada',
};
export const colores: Record<Estado, string> = {
  confirmada: 'cyan-9', completada: 'green-9', cancelada: 'red-8',
};
export const dinero = (importe: number | string) => new Intl.NumberFormat('es-PE', {
  style: 'currency', currency: 'PEN',
}).format(Number(importe));
export function horario(valor: string) {
  const [anio, mes, dia] = valor.slice(0, 10).split('-');
  return dia + '/' + mes + '/' + anio + ' · ' + valor.slice(11, 16);
}
