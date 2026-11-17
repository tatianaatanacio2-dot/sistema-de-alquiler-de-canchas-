export type Metodo = 'efectivo' | 'yape' | 'plin' | 'transferencia' | 'tarjeta';
export type Pago = {
  id: number; reserva_id: number; tipo: 'cobro' | 'devolucion'; monto: string;
  metodo: Metodo; nota: string; fecha: string;
};
export type Totales = { ingresos: string; devoluciones: string; gastos: string; neto: string };
export type Movimiento = {
  clave: string; id: number; fecha: string; tipo: 'cobro' | 'devolucion' | 'gasto';
  concepto: string; metodo: Metodo; monto: string; anulado: boolean;
};
export type Caja = { fecha: string; totales: Totales; porMetodo: Record<Metodo, Totales>; movimientos: Movimiento[] };
export type Cambio = {
  id: number; fecha: string; usuario: string; correo: string | null; entidad: string;
  registro_id: number; accion: string; antes: Record<string, unknown> | null; despues: Record<string, unknown>;
};
export const metodos = [
  { label: 'Efectivo', value: 'efectivo' },
  { label: 'Yape', value: 'yape' },
  { label: 'Plin', value: 'plin' },
  { label: 'Transferencia', value: 'transferencia' },
  { label: 'Tarjeta', value: 'tarjeta' },
];
export const nombreMetodo = (valor: string) => metodos.find(item => item.value === valor)?.label || valor;
export const validarMonto = (valor: string) =>
  /^\d{1,8}(\.\d{1,2})?$/.test(String(valor)) && Number(valor) > 0 || 'Ingresa un importe positivo con hasta dos decimales.';
export const fechaLocal = () => new Intl.DateTimeFormat('sv-SE', {
  timeZone: 'America/Bogota', year: 'numeric', month: '2-digit', day: '2-digit',
}).format(new Date());
