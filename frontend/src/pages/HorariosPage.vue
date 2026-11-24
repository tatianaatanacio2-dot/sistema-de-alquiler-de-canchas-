<template>
  <q-layout view="hHh lpR fFf" class="tema-publico" :class="{ 'tema-claro': !oscuro }">
    <q-page-container>
      <q-page class="horarios-publicos">
        <div class="contenido">
          <header class="cabecera">
            <div class="marca">
              <q-avatar color="green-8" text-color="white" icon="sports_soccer" />
              <span>{{ canchaElegida?.nombre ?? 'Canchas sintéticas' }}</span>
            </div>
            <div class="acciones-cabecera">
              <q-btn outline no-caps label="Apariencia" :icon="oscuro ? 'dark_mode' : 'light_mode'" icon-right="expand_more">
                <q-menu :dark="oscuro" class="tema-publico menu-apariencia" :class="{ 'tema-claro': !oscuro }">
                  <q-list role="menu" aria-label="Elegir apariencia">
                    <q-item v-for="opcion in temas" :key="opcion.valor" v-close-popup clickable
                      role="menuitemradio" :aria-checked="tema === opcion.valor" @click="elegirTema(opcion.valor)">
                      <q-item-section avatar><q-icon :name="opcion.icono" /></q-item-section>
                      <q-item-section>{{ opcion.nombre }}</q-item-section>
                      <q-item-section side><q-icon v-if="tema === opcion.valor" name="check" :color="colorAcento" /></q-item-section>
                    </q-item>
                  </q-list>
                </q-menu>
              </q-btn>
              <q-btn to="/ingresar" outline no-caps label="Administración" icon="lock_outline" />
            </div>
          </header>

          <q-linear-progress v-if="cargandoCanchas" indeterminate color="green-8" aria-label="Cargando canchas" />
          <q-banner v-else-if="errorCanchas" class="q-mt-lg bg-red-1 text-negative" role="alert">
            {{ errorCanchas }}
            <template #action><q-btn flat no-caps label="Reintentar" @click="cargarCanchas" /></template>
          </q-banner>

          <section v-else-if="!enDetalle" class="seleccion-canchas" aria-labelledby="titulo-canchas">
            <div class="intro-canchas">
              <span class="etiqueta-portada">TU PRÓXIMO PARTIDO EMPIEZA AQUÍ</span>
              <h1 id="titulo-canchas">Elige tu <span>cancha.</span></h1>
              <p>Escoge uno de nuestros {{ canchas.length }} gras sintéticos para ver sus horarios y reservar.</p>
            </div>
            <div class="lista-canchas">
              <router-link v-for="cancha in canchas" :key="cancha.id" class="tarjeta-cancha"
                :to="{ path: '/horarios', query: { cancha: cancha.id } }"
                :aria-label="'Ver horarios de ' + cancha.nombre">
                <img :src="fotoDeCancha(cancha.id)" :alt="'Imagen ilustrativa de ' + cancha.nombre"
                  width="1672" height="941" :loading="cancha.id > 3 ? 'lazy' : 'eager'" />
                <div class="tarjeta-contenido">
                  <h2>{{ cancha.nombre }}</h2>
                  <p class="tipo-cancha">Césped sintético</p>
                  <div class="tarjeta-pie">
                    <span v-if="cancha.tarifa !== null" class="precio-cancha">{{ dinero(cancha.tarifa) }} <small>/ hora</small></span>
                    <span class="ver-cancha"><q-icon name="calendar_month" size="18px" />{{ cancha.activa ? 'Ver horarios' : 'Fuera de servicio' }}</span>
                  </div>
                </div>
              </router-link>
            </div>
            <p v-if="!canchas.length" class="nota-imagenes">Todavía no hay canchas disponibles.</p>
            <p v-else class="nota-imagenes">Las imágenes son ilustrativas.</p>
          </section>

          <template v-else>
          <q-btn class="volver-canchas" flat no-caps icon="grid_view" label="Todas las canchas" to="/horarios" />
          <q-banner v-if="!canchaElegida" class="bg-red-1 text-negative" role="alert">No se encontró esa cancha. Elige una de las canchas disponibles.</q-banner>
          <template v-else>
          <section class="portada">
            <div class="portada-texto">
              <span class="etiqueta-portada">CÉSPED SINTÉTICO</span>
              <h1>{{ canchaElegida.nombre }}</h1>
              <p>Reúne a tu equipo. Consulta los horarios de esta cancha y elige cuándo jugar.</p>
              <div class="acciones-portada">
                <q-btn unelevated no-caps :color="colorAcento" :text-color="oscuro ? 'black' : 'white'" label="Ver horarios disponibles"
                  icon="calendar_month" @click="verAgenda" />
                <q-btn v-if="agenda?.whatsapp" outline no-caps :color="colorAcento"
                  label="Consultar por WhatsApp" icon="chat"
                  :href="contactoWhatsApp" target="_blank" rel="noopener noreferrer" />
                <p v-if="agenda?.cancha.tarifa" class="tarifa">{{ dinero(agenda.cancha.tarifa) }}<small>por hora</small></p>
              </div>
            </div>
            <figure class="foto-detalle">
              <img class="foto-cancha" :src="fotoDeCancha(canchaElegida.id)" :alt="'Imagen ilustrativa de ' + canchaElegida.nombre" fetchpriority="high" width="1672" height="941" />
              <figcaption>Imagen ilustrativa</figcaption>
            </figure>
          </section>

          <section id="agenda-publica" class="agenda" aria-labelledby="titulo-agenda-publica" :aria-busy="cargando">
            <div class="agenda-cabecera">
              <div>
                <h2 id="titulo-agenda-publica">{{ vista === 'semana' ? 'Horario semanal' : 'Horario diario' }}</h2>
                <p v-if="agenda">{{ vista === 'semana' ? rangoSemana : dia(agenda.fecha) }} · Hora de Perú</p>
              </div>
              <div v-if="agenda" class="controles">
                <q-btn-toggle :model-value="vista" :options="vistas" :disable="cargando"
                  no-caps unelevated toggle-color="green-9" @update:model-value="cambiarVista" />
                <q-btn outline no-caps class="selector-fecha" :disable="cargando"
                  :aria-label="'Elegir fecha: ' + dia(fecha)">
                  <span class="fecha-texto"><span>Elegir fecha</span><strong>{{ fecha.split('-').reverse().join('/') }}</strong></span>
                  <q-icon name="calendar_month" size="20px" />
                  <q-popup-proxy v-model="calendarioAbierto" transition-show="scale" transition-hide="scale">
                    <q-date :model-value="fecha" mask="YYYY-MM-DD" :dark="oscuro" color="green-8" no-unset
                      :options="fechaDisponible"
                      :navigation-min-year-month="agenda.hoy.slice(0, 7).replace('-', '/')"
                      :navigation-max-year-month="agenda.limite.slice(0, 7).replace('-', '/')"
                      @update:model-value="elegirFecha">
                      <div class="row justify-end">
                        <q-btn v-close-popup flat no-caps :color="colorAcento" label="Cerrar" />
                      </div>
                    </q-date>
                  </q-popup-proxy>
                </q-btn>
              </div>
            </div>
            <q-linear-progress v-if="cargando" indeterminate color="teal-8" />
            <q-banner v-else-if="error" class="bg-red-1 text-negative" role="alert">
              {{ error }}
              <template #action><q-btn flat no-caps label="Reintentar" @click="cargar(fecha)" /></template>
            </q-banner>
            <div v-else-if="!agenda?.tramos.length" class="sin-turnos">La cancha está fuera de servicio.</div>
            <template v-else-if="vista === 'semana'">
              <div class="cuadricula" role="region" aria-label="Tabla de horarios semanales" tabindex="0">
                <table>
                  <thead>
                    <tr>
                      <th scope="col">Hora</th>
                      <th v-for="entrada in agenda.semana" :key="entrada.fecha" scope="col">
                        <button v-if="entrada.consultable" type="button" class="enlace-dia"
                          :aria-label="'Ver horario diario del ' + dia(entrada.fecha)"
                          @click="abrirDia(entrada.fecha)">{{ diaCorto(entrada.fecha) }}</button>
                        <span v-else>{{ diaCorto(entrada.fecha) }}</span>
                      </th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="fila in filas" :key="fila.inicio">
                      <th scope="row" class="horas">{{ fila.inicio }}–{{ fila.fin }}</th>
                      <td v-for="(estado, i) in fila.estados" :key="i" :class="estado">
                        <button v-if="estado === 'libre'" type="button" class="turno-libre"
                          :aria-label="'Seleccionar horario libre del ' + dia(agenda.semana[i]!.fecha) + ' de ' + fila.inicio + ' a ' + fila.fin"
                          @click="seleccionar(agenda.semana[i]!.fecha, fila.inicio, fila.fin)">Libre</button>
                        <template v-else>{{ estado === 'sinHorario' ? '—' : estados[estado] }}</template>
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
              <p class="indicio">Pulsa un horario libre para reservar.</p>
            </template>
            <ul v-else class="turnos">
              <li v-for="tramo in agenda.tramos" :key="tramo.inicio" class="turno">
                <span class="horas">{{ tramo.inicio }}–{{ tramo.fin }}</span>
                <button v-if="tramo.estado === 'libre'" type="button" class="estado turno-libre libre"
                  :aria-label="'Seleccionar horario libre de ' + tramo.inicio + ' a ' + tramo.fin"
                  @click="seleccionar(agenda.fecha, tramo.inicio, tramo.fin)">Libre</button>
                <span v-else class="estado" :class="tramo.estado">{{ estados[tramo.estado] }}</span>
              </li>
            </ul>
          </section>
          </template>
          </template>
        </div>
      </q-page>
    </q-page-container>
    <q-dialog v-model="dialogo">
      <q-card v-if="seleccion" :dark="oscuro" class="tema-publico reserva-dialogo" :class="{ 'tema-claro': !oscuro }">
        <q-card-section>
          <h2 class="text-h5 q-mt-none q-mb-md">Reservar horario</h2>
          <p class="nombre-reserva"><q-icon name="sports_soccer" /> {{ agenda?.cancha.nombre }}</p>
          <p>{{ dia(seleccion.fecha) }}</p>
          <p class="text-h6">{{ seleccion.inicio }}–{{ seleccion.fin }}</p>
          <p v-if="agenda?.cancha.tarifa">Total del turno: {{ dinero(totalSeleccion) }}</p>
          <p v-if="agenda?.whatsapp">Envía la solicitud por WhatsApp para que la administración confirme tu reserva.</p>
          <p v-else>La cancha debe configurar su número de WhatsApp para recibir la solicitud de reserva.</p>
        </q-card-section>
        <q-card-actions align="right">
          <q-btn v-close-popup flat no-caps label="Cerrar" />
          <q-btn :disable="!agenda?.whatsapp" :href="reservaWhatsApp || undefined" target="_blank"
            rel="noopener noreferrer" unelevated no-caps color="green-8" icon="chat" label="Reservar por WhatsApp" />
        </q-card-actions>
      </q-card>
    </q-dialog>
  </q-layout>
</template>

<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue';
import { useRoute } from 'vue-router';
import fotoCancha from '@/assets/cancha-atardecer.png';
import fotoCancha2 from '@/assets/cancha-2.png';
import fotoCancha3 from '@/assets/cancha-3.png';
import fotoCancha4 from '@/assets/cancha-4.png';
import fotoCancha5 from '@/assets/cancha-5.png';
import { api, dinero, type Cancha } from '@/services/api';

const route = useRoute();
const canchas = ref<Cancha[]>([]);
const cargandoCanchas = ref(true);
const errorCanchas = ref('');
const enDetalle = computed(() => route.query.cancha !== undefined);
const canchaElegida = computed(() => {
  const valor = route.query.cancha;
  if (typeof valor !== 'string' || !/^[1-9]\d*$/.test(valor)) return undefined;
  return canchas.value.find(cancha => cancha.id === Number(valor));
});
const fotos: Record<number, string> = { 1: fotoCancha, 2: fotoCancha2, 3: fotoCancha3, 4: fotoCancha4, 5: fotoCancha5 };
const fotoDeCancha = (id: number) => fotos[id] ?? fotoCancha;

const estados = { libre: 'Libre', reservado: 'Reservado', pasado: 'Pasado' };
type Tramo = { inicio: string; fin: string; estado: keyof typeof estados };
type Vista = 'semana' | 'dia';
type Tema = 'oscuro' | 'claro';
const temas: { valor: Tema; nombre: string; icono: string }[] = [
  { valor: 'oscuro', nombre: 'Modo oscuro', icono: 'dark_mode' },
  { valor: 'claro', nombre: 'Modo claro', icono: 'light_mode' },
];
const tema = ref<Tema>(leerTema());
const oscuro = computed(() => tema.value === 'oscuro');
const colorAcento = computed(() => oscuro.value ? 'light-green-5' : 'green-9');

function leerTema(): Tema {
  try {
    return localStorage.getItem('canchas.apariencia') === 'claro' ? 'claro' : 'oscuro';
  } catch {
    return 'oscuro';
  }
}

function elegirTema(valor: Tema) {
  tema.value = valor;
  try {
    localStorage.setItem('canchas.apariencia', valor);
  } catch {
    // Mantiene la elección durante esta visita si el navegador impide guardarla.
  }
}
type DiaAgenda = { fecha: string; consultable: boolean; tramos: Tramo[] };
type Agenda = {
  cancha: Cancha;
  fecha: string;
  hoy: string;
  limite: string;
  apertura: string;
  cierre: string;
  whatsapp: string | null;
  tramos: Tramo[];
  semana: DiaAgenda[];
};
const agenda = ref<Agenda>();
const fecha = ref('');
const error = ref('');
const cargando = ref(false);
const calendarioAbierto = ref(false);
const vista = ref<Vista>('semana');
const dialogo = ref(false);
const seleccion = ref<{ fecha: string; inicio: string; fin: string }>();
const totalSeleccion = computed(() => {
  if (!seleccion.value || !agenda.value?.cancha.tarifa) return 0;
  const minutos = (hora: string) => Number(hora.slice(0, 2)) * 60 + Number(hora.slice(3));
  return (minutos(seleccion.value.fin) - minutos(seleccion.value.inicio)) * agenda.value.cancha.tarifa / 60;
});
const reservaWhatsApp = computed(() => {
  const datos = seleccion.value;
  if (!datos || !agenda.value?.whatsapp) return '';
  const texto = 'Hola, quisiera reservar ' + agenda.value.cancha.nombre + ' (cancha ' + agenda.value.cancha.id + ') el ' + dia(datos.fecha) + ' de ' + datos.inicio + ' a ' + datos.fin + '. ¿Me confirman la disponibilidad?';
  return 'https://wa.me/' + agenda.value.whatsapp + '?text=' + encodeURIComponent(texto);
});

function seleccionar(fecha: string, inicio: string, fin: string) {
  seleccion.value = { fecha, inicio, fin };
  dialogo.value = true;
}
const contactoWhatsApp = computed(() => agenda.value?.whatsapp
  ? 'https://wa.me/' + agenda.value.whatsapp + '?text=' + encodeURIComponent('Hola, quisiera consultar una reserva para ' + agenda.value.cancha.nombre + ' (cancha ' + agenda.value.cancha.id + ').')
  : '');
const vistas = [{ label: 'Semana', value: 'semana' }, { label: 'Día', value: 'dia' }];
function verAgenda() {
  document.getElementById('agenda-publica')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
}
const formatoDia = new Intl.DateTimeFormat('es-PE', { dateStyle: 'full', timeZone: 'UTC' });
const dia = (valor: string) => formatoDia.format(new Date(valor + 'T00:00:00Z'));
const formatoCorto = new Intl.DateTimeFormat('es-PE', { weekday: 'short', day: 'numeric', timeZone: 'UTC' });
const diaCorto = (valor: string) => formatoCorto.format(new Date(valor + 'T00:00:00Z'));
const rangoSemana = computed(() => {
  const semana = agenda.value?.semana;
  const primero = semana?.[0];
  const ultimo = semana?.[6];
  return primero && ultimo ? diaCorto(primero.fecha) + ' – ' + diaCorto(ultimo.fecha) + ' · ' + ultimo.fecha.slice(0, 7).split('-').reverse().join('/') : '';
});

const filas = computed(() => {
  const datos = agenda.value;
  if (!datos?.semana.length) return [];

  const cortes = new Set([datos.apertura, datos.cierre]);
  for (let hora = Number(datos.apertura.slice(0, 2)) + 1; hora < 24; hora++) {
    const turno = String(hora).padStart(2, '0') + ':00';
    if (turno >= datos.cierre) break;
    cortes.add(turno);
  }
  for (const entrada of datos.semana) {
    for (const tramo of entrada.tramos) {
      cortes.add(tramo.inicio);
      cortes.add(tramo.fin);
    }
  }
  const horas = [...cortes].sort();
  return horas.slice(1).map((fin, i) => {
    const inicio = horas[i]!;
    const estados = datos.semana.map(entrada => {
      const tramo = entrada.tramos.find(tramo => tramo.inicio <= inicio && tramo.fin >= fin);
      return tramo?.estado ?? 'sinHorario';
    });
    return { inicio, fin, estados };
  });
});

let peticionActual = 0;
async function cargar(valor = '') {
  const cancha = canchaElegida.value;
  if (!cancha) return;
  const peticion = ++peticionActual;
  cargando.value = true;
  error.value = '';
  try {
    const filtros = new URLSearchParams({ vista: vista.value, cancha_id: String(cancha.id) });
    if (valor) filtros.set('fecha', valor);
    const resultado = await api<Agenda>('/horarios?' + filtros);
    if (peticion !== peticionActual) return;
    agenda.value = resultado;
    fecha.value = agenda.value.fecha;
  } catch (fallo) {
    if (peticion !== peticionActual) return;
    error.value = fallo instanceof Error ? fallo.message : 'No se pudo consultar la disponibilidad.';
  } finally {
    if (peticion === peticionActual) cargando.value = false;
  }
}

function cargarSeleccion() {
  ++peticionActual;
  agenda.value = undefined;
  seleccion.value = undefined;
  dialogo.value = false;
  calendarioAbierto.value = false;
  cargando.value = false;
  fecha.value = '';
  error.value = '';
  if (!cargandoCanchas.value && canchaElegida.value) void cargar();
}

async function cargarCanchas() {
  cargandoCanchas.value = true;
  errorCanchas.value = '';
  try {
    canchas.value = await api<Cancha[]>('/canchas');
  } catch (fallo) {
    errorCanchas.value = fallo instanceof Error ? fallo.message : 'No se pudieron cargar las canchas.';
  } finally {
    cargandoCanchas.value = false;
    if (!errorCanchas.value) cargarSeleccion();
  }
}

function cambiarVista(valor: Vista) {
  if (valor === vista.value || cargando.value) return;
  vista.value = valor;
  void cargar(fecha.value);
}

function abrirDia(valor: string) {
  if (cargando.value) return;
  vista.value = 'dia';
  cambiarDia(valor);
}

function cambiarDia(valor: string | number | null) {
  if (typeof valor !== 'string' || !valor || !agenda.value || cargando.value) return;
  if (valor < agenda.value.hoy || valor > agenda.value.limite) return;
  fecha.value = valor;
  void cargar(valor);
}

function fechaDisponible(valor: string) {
  const fechaDia = valor.replaceAll('/', '-');
  return !!agenda.value && fechaDia >= agenda.value.hoy && fechaDia <= agenda.value.limite;
}

function elegirFecha(valor: string | null) {
  calendarioAbierto.value = false;
  cambiarDia(valor);
}

watch(() => route.query.cancha, cargarSeleccion);
onMounted(cargarCanchas);
</script>

<style scoped>
.tema-publico {
  color-scheme: dark;
  --pagina-fondo: #0b0f17;
  --superficie: #111a24;
  --texto: #f1f5f9;
  --secundario: #aab9c4;
  --acento: #b2e978;
  --borde: #2b3944;
  --cabecera-tabla: #202f3d;
  --libre-fondo: #163e30;
  --libre-texto: #b6e9cd;
  --reservado-fondo: #49352a;
  --reservado-texto: #f2c59e;
  --pasado-fondo: #1d2833;
  --pasado-texto: #a6b4c0;
  --fuera-fondo: #17212b;
  --fuera-texto: #91a0ae;
  --hover: #ffffff12;
  --sombra: #0005;
}
.tema-claro {
  color-scheme: light;
  --pagina-fondo: #f4f7f5;
  --superficie: #fff;
  --texto: #1d342b;
  --secundario: #51665c;
  --acento: #236438;
  --borde: #cedcd3;
  --cabecera-tabla: #e4ece7;
  --libre-fondo: #dcefe2;
  --libre-texto: #225336;
  --reservado-fondo: #f5e5d0;
  --reservado-texto: #70441b;
  --pasado-fondo: #e8ecea;
  --pasado-texto: #52635a;
  --fuera-fondo: #edf1ee;
  --fuera-texto: #64736a;
  --hover: #1438240c;
  --sombra: #153c2518;
}
.horarios-publicos { background: var(--pagina-fondo); color: var(--texto); padding: 0 20px 48px; }
.menu-apariencia { background: var(--superficie); color: var(--texto); min-width: 200px; }
.menu-apariencia :deep(.q-item__section--side) { color: var(--secundario); }
.contenido { max-width: 1120px; margin: auto; }
.cabecera, .marca, .agenda-cabecera, .turno { display: flex; align-items: center; gap: 12px; }
.cabecera, .agenda-cabecera { justify-content: space-between; flex-wrap: wrap; }
.cabecera { padding: 20px 0; border-bottom: 1px solid var(--borde); }
.acciones-cabecera { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; }
.marca { font-weight: 700; }
.seleccion-canchas { padding-top: 48px; }
.intro-canchas { margin-bottom: 32px; }
.etiqueta-portada { display: block; color: var(--acento); font-size: .8rem; font-weight: 700; letter-spacing: .1em; margin-bottom: 16px; }
.intro-canchas h1 { font-size: clamp(2.5rem, 6vw, 4rem); line-height: 1.1; font-weight: 800; letter-spacing: -.04em; margin: 0 0 16px; }
.intro-canchas h1 span { color: var(--acento); }
.intro-canchas p { color: var(--secundario); font-size: 1rem; margin: 0; }
.lista-canchas { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 24px; }
.tarjeta-cancha { display: block; text-decoration: none; color: var(--texto); background: var(--superficie); border: 1px solid var(--borde); border-radius: 18px; overflow: hidden; transition: border-color .2s, box-shadow .2s; }
.tarjeta-cancha:hover { border-color: var(--acento); box-shadow: 0 8px 28px var(--sombra); }
.tarjeta-cancha:focus-visible { outline: 3px solid var(--acento); outline-offset: 4px; }
.tarjeta-cancha > img { width: 100%; height: auto; aspect-ratio: 16 / 9; object-fit: cover; display: block; }
.tarjeta-contenido { padding: 20px; }
.tarjeta-contenido h2 { margin: 0 0 4px; font-size: 1.35rem; line-height: 1.3; font-weight: 700; }
.tipo-cancha { color: var(--secundario); font-size: .9rem; margin: 0 0 20px; }
.tarjeta-pie { display: flex; justify-content: space-between; align-items: center; gap: 12px; flex-wrap: wrap; }
.precio-cancha { font-weight: 700; font-size: 1rem; }
.precio-cancha small { font-weight: 400; color: var(--secundario); font-size: .875rem; }
.ver-cancha { display: flex; gap: 6px; align-items: center; color: var(--acento); font-size: .875rem; font-weight: 600; }
.nota-imagenes { font-size: .8rem; color: var(--secundario); margin-top: 20px; }
.volver-canchas { margin-top: 24px; }
.portada { display: grid; grid-template-columns: 1.4fr 1fr; align-items: center; gap: 32px; padding: 32px 0 48px; }
.portada h1 { font-size: clamp(2.2rem, 5vw, 4.4rem); line-height: 1.07; font-weight: 800; letter-spacing: -.045em; margin: 0 0 20px; }
.portada h1 span { color: var(--acento); }
.portada p { margin: 0; font-size: 1rem; }
.portada-texto > p { color: var(--secundario); max-width: 370px; }
.acciones-portada { display: flex; gap: 24px; align-items: center; flex-wrap: wrap; margin-top: 28px; }
.portada .tarifa { color: var(--texto); font-size: 1.35rem; font-weight: 700; }
.tarifa small { display: block; font-size: .8rem; color: var(--secundario); font-weight: 400; }
.foto-cancha { display: block; width: 100%; height: 300px; object-fit: cover; border-radius: 24px; box-shadow: 0 16px 60px var(--sombra); }
.foto-detalle { margin: 0; }
.foto-detalle figcaption { text-align: right; font-size: .75rem; color: var(--secundario); margin-top: 8px; }
.nombre-reserva { font-size: 1.1rem; font-weight: 700; color: var(--acento); }
.agenda { border: 1px solid var(--borde); border-radius: 18px; background: var(--superficie); overflow: hidden; scroll-margin-top: 16px; }
.agenda-cabecera, .sin-turnos { padding: 16px 20px; }
.agenda h2 { margin: 0; font-size: 1.3rem; line-height: 1.3; font-weight: 700; }
.agenda p { margin: 4px 0 0; color: var(--secundario); }
.turnos { list-style: none; margin: 0; padding: 0; }
.turno { padding: 10px 20px; border-top: 1px solid var(--borde); min-height: 55px; flex-wrap: wrap; }
.turno .q-btn { margin-left: auto; }
.horas { font-variant-numeric: tabular-nums; font-weight: 700; }
.estado { border-radius: 20px; padding: 4px 10px; font-size: .85rem; font-weight: 600; }
.libre { background: var(--libre-fondo); color: var(--libre-texto); }
.reservado { background: var(--reservado-fondo); color: var(--reservado-texto); }
.pasado { background: var(--pasado-fondo); color: var(--pasado-texto); }
.controles { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; }
.selector-fecha { border-radius: 8px; text-align: left; }
.fecha-texto { display: flex; flex-direction: column; margin-right: 16px; }
.fecha-texto > span { font-size: .75rem; color: var(--secundario); }
.fecha-texto strong { font-size: .9rem; font-variant-numeric: tabular-nums; }
.cuadricula { overflow-x: auto; padding: 0 12px; }
.cuadricula table { width: 100%; min-width: 690px; border-spacing: 4px; text-align: center; table-layout: fixed; }
.cuadricula th, .cuadricula td { padding: 8px 4px; border-radius: 6px; font-size: .85rem; }
.cuadricula th { color: var(--texto); background: var(--cabecera-tabla); }
.cuadricula thead th { text-transform: capitalize; }
.cuadricula th:first-child { position: sticky; left: 0; }
.cuadricula .sinHorario { background: var(--fuera-fondo); color: var(--fuera-texto); }
.enlace-dia { border: 0; background: transparent; color: inherit; cursor: pointer; font: inherit; text-transform: inherit; text-decoration: underline; padding: 4px; }
.agenda .indicio { padding: 0 16px 14px; font-size: .85rem; }
.turno-libre { border: 0; color: inherit; background: transparent; font: inherit; cursor: pointer; padding: 8px; text-decoration: underline; }
.cuadricula .turno-libre { width: 100%; min-height: 36px; }
.turno-libre:hover { background: var(--hover); }
.turno-libre:focus-visible { outline: 2px solid var(--acento); outline-offset: -2px; }
.reserva-dialogo { width: 430px; max-width: 100%; background: var(--superficie); color: var(--texto); border: 1px solid var(--borde); }
@media (max-width: 1000px) {
  .lista-canchas { grid-template-columns: repeat(2, minmax(0, 1fr)); }
}
@media (max-width: 650px) {
  .lista-canchas { grid-template-columns: 1fr; gap: 20px; }
  .seleccion-canchas { padding-top: 32px; }
  .portada { grid-template-columns: 1fr; padding: 32px 0; gap: 28px; }
  .foto-cancha { height: auto; aspect-ratio: 16 / 9; }
  .cabecera { gap: 16px; }
  .marca { flex: 1; min-width: 180px; }
}
</style>
