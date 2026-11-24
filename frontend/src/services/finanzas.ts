import type { Reserva } from "./api";

export type Metodo = "efectivo" | "yape" | "plin" | "transferencia" | "tarjeta";
export type Pago = {
  id: number;
  reserva_id: number;
  tipo: "cobro" | "devolucion";
  monto: string;
  metodo: Metodo;
  nota: string;
  fecha: string;
};
export type Totales = {
  ingresos: string;
  devoluciones: string;
  gastos: string;
  neto: string;
};
export type Movimiento = {
  clave: string;
  id: number;
  fecha: string;
  tipo: "cobro" | "devolucion" | "gasto";
  concepto: string;
  metodo: Metodo;
  monto: string;
  anulado: boolean;
};
export type Caja = {
  fecha: string;
  totales: Totales;
  porMetodo: Record<Metodo, Totales>;
  movimientos: Movimiento[];
};
export type Cambio = {
  id: number;
  fecha: string;
  usuario: string;
  correo: string | null;
  entidad: string;
  registro_id: number;
  accion: string;
  antes: Record<string, unknown> | null;
  despues: Record<string, unknown>;
};
export const metodos = [
  { label: "Efectivo", value: "efectivo" },
  { label: "Yape", value: "yape" },
  { label: "Plin", value: "plin" },
  { label: "Transferencia", value: "transferencia" },
  { label: "Tarjeta", value: "tarjeta" }
];
export const nombreMetodo = (valor: string) =>
  metodos.find(item => item.value === valor)?.label || valor;
export const saldoReserva = (reserva: Reserva) =>
  reserva.estado === "cancelada"
    ? Number(reserva.abonado)
    : Math.max(
        0,
        Math.round((Number(reserva.total) - Number(reserva.abonado)) * 100) /
          100
      );
export function estadoPago(reserva: Reserva) {
  if (reserva.estado === "cancelada") {
    return Number(reserva.abonado) > 0
      ? { label: "Por devolver", color: "orange-9" }
      : { label: "Sin saldo", color: "grey-7" };
  }
  if (Number(reserva.abonado) >= Number(reserva.total))
    return { label: "Pagado", color: "green-9" };
  return Number(reserva.abonado) > 0
    ? { label: "Adelanto", color: "blue-8" }
    : { label: "Pendiente", color: "orange-9" };
}
export const validarMonto = (valor: string) =>
  (/^\d{1,8}(\.\d{1,2})?$/.test(String(valor)) && Number(valor) > 0) ||
  "Ingresa un importe positivo con hasta dos decimales.";
export const fechaLocal = () =>
  new Intl.DateTimeFormat("sv-SE", {
    timeZone: "America/Lima",
    year: "numeric",
    month: "2-digit",
    day: "2-digit"
  }).format(new Date());
