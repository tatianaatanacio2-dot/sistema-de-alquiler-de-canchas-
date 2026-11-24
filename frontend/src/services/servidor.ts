export function normalizarServidor(
  direccion: string,
  admiteHttpLocal = true
): string {
  const valor = direccion.trim();
  const mensaje = admiteHttpLocal
    ? "Ingresa una dirección HTTPS o una IP privada, por ejemplo http://192.168.1.10:8012."
    : "Ingresa la dirección HTTPS del servidor.";
  const tieneProtocolo = valor.includes("://");
  let url: URL;
  try {
    url = new URL(tieneProtocolo ? valor : `https://${valor}`);
  } catch {
    throw new Error(mensaje);
  }

  const privada =
    /^(?:10\.\d+|192\.168|172\.(?:1[6-9]|2\d|3[01]))\.\d+\.\d+$/.test(
      url.hostname
    );
  if (!tieneProtocolo && privada && admiteHttpLocal)
    url = new URL(`http://${valor}`);
  if (
    /[\s\\?#]/.test(valor) ||
    url.username ||
    url.password ||
    url.pathname !== "/" ||
    !(
      url.protocol === "https:" ||
      (url.protocol === "http:" && privada && admiteHttpLocal)
    )
  ) {
    throw new Error(mensaje);
  }
  return url.origin;
}
