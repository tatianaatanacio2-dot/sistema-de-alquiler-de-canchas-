import { reactive, ref } from 'vue';
import { normalizarServidor } from './servidor';

export type Usuario = { nombre: string; correo: string };
export type Cancha = { id: number; nombre: string; tarifa: number | null; activa: number | boolean };
export type Cliente = { id: number; dni: string; nombre: string; telefono: string; correo: string };
export type Estado = 'confirmada' | 'cancelada' | 'completada';
export type Reserva = {
  id: number; cliente_id: number | null; cliente: string; cliente_telefono?: string | null; inicio: string; fin: string;
  tarifa: number; total: string; estado: Estado; abonado: number | string; metodo_pago: string | null;
  pagado?: number | string; saldo?: number | string;
};
export type Pago = { id: number; reserva_id: number; monto: string; metodo: string; referencia: string | null; registrado_en: string };
export type EntradaEspera = {
  id: number; cliente_id: number; cliente: string; inicio: string; fin: string;
  estado: 'pendiente' | 'disponible' | 'avisado' | 'atendida' | 'cancelada';
};
export type Acceso = { requiereCuenta: boolean; equipoLocal: boolean };
type Credenciales = { token: string; usuario: Usuario };

export const android = import.meta.env.QUASAR_CAPACITOR_MODE;
const admiteHttpLocal = !import.meta.env.CANCHAS_PUBLICACION;
function leerServidorGuardado() {
  const direccion = localStorage.getItem('canchas.servidor');
  try {
    return direccion ? normalizarServidor(direccion, admiteHttpLocal) : '';
  } catch {
    return '';
  }
}

const servidorGuardado = android ? leerServidorGuardado() : '';
export const servidor = ref(android ? servidorGuardado || import.meta.env.CANCHAS_SERVIDOR : '');
export const session = reactive<{ usuario: Usuario | null; lista: boolean }>({ usuario: null, lista: false });
let token = sessionStorage.getItem('canchas.token') || '';
if (android && !servidorGuardado) cerrarSesion();

export class ErrorApi extends Error {
  constructor(message: string, readonly estado: number) {
    super(message);
  }
}

export async function conectar(direccion: string) {
  const origen = normalizarServidor(direccion, admiteHttpLocal);
  const acceso = await solicitar<Acceso>(origen, '/acceso', 'GET');
  if (typeof acceso.requiereCuenta !== 'boolean' || typeof acceso.equipoLocal !== 'boolean') {
    throw new Error('Esta dirección no corresponde a un servidor de Canchas compatible.');
  }
  if (origen !== servidor.value) cerrarSesion();
  localStorage.setItem('canchas.servidor', origen);
  servidor.value = origen;
  return acceso;
}

async function solicitar<T>(origen: string, ruta: string, method: string, datos?: unknown, credencial = ''): Promise<T> {
  const url = origen + '/api' + ruta;
  const headers = {
    Accept: 'application/json',
    ...(datos === undefined ? {} : { 'Content-Type': 'application/json' }),
    ...(credencial ? { Authorization: 'Bearer ' + credencial } : {}),
  };
  let estado: number;
  let contenido;
  try {
    if (import.meta.env.QUASAR_CAPACITOR_MODE) {
      const { CapacitorHttp } = await import('@capacitor/core');
      const respuesta = await CapacitorHttp.request({
        url, method, headers, data: datos, connectTimeout: 8000, readTimeout: 12000, disableRedirects: true,
      });
      estado = respuesta.status;
      contenido = respuesta.data;
    } else {
      const controlador = new AbortController();
      const limite = setTimeout(() => controlador.abort(), 12000);
      try {
        const respuesta = await fetch(url, {
          method, headers, body: datos === undefined ? null : JSON.stringify(datos), signal: controlador.signal,
          redirect: 'error',
        });
        estado = respuesta.status;
        contenido = await respuesta.json().catch(() => null);
      } finally {
        clearTimeout(limite);
      }
    }
  } catch {
    throw new Error('No se pudo conectar. Verifica tu conexión y la dirección del servidor.');
  }
  if (estado < 200 || estado >= 300 || typeof contenido !== 'object' || contenido === null) {
    const validacion = contenido?.errors && Object.values(contenido.errors).flat().join(' ');
    const mensaje = estado === 429 ? 'Demasiados intentos. Espera un minuto y vuelve a intentar.'
      : validacion || contenido?.message || 'El servidor no pudo completar la operación.';
    throw new ErrorApi(mensaje, estado);
  }
  return contenido as T;
}

export async function api<T>(ruta: string, method = 'GET', datos?: unknown): Promise<T> {
  const origen = servidor.value;
  if (android && !origen) throw new Error('Conecta primero con el servidor de Canchas.');
  const credencial = token;
  try {
    return await solicitar<T>(origen, ruta, method, datos, credencial);
  } catch (problema) {
    if (problema instanceof ErrorApi && problema.estado === 401 && credencial === token
      && origen === servidor.value) cerrarSesion();
    throw problema;
  }
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
    catch { /* La API elimina el token si el servidor rechaza la sesión. */ }
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
const moneda = new Intl.NumberFormat('es-PE', {
  style: 'currency', currency: 'PEN',
});
export const dinero = (importe: number | string) => moneda.format(Number(importe));
export function horario(valor: string) {
  const [anio, mes, dia] = valor.slice(0, 10).split('-');
  return dia + '/' + mes + '/' + anio + ' · ' + valor.slice(11, 16);
}
