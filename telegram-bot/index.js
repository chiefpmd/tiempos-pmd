require('dotenv').config();
const TelegramBot = require('node-telegram-bot-api');
const db = require('./db');

const bot = new TelegramBot(process.env.TELEGRAM_BOT_TOKEN, { polling: true });
const CHAT_ID = process.env.TELEGRAM_CHAT_ID;

function isAuth(msg) { return String(msg.chat.id) === String(CHAT_ID); }

// Get current date/time in CDMX timezone
function cdmxNow() { return new Date(new Date().toLocaleString('en-US', { timeZone: 'America/Mexico_City' })); }
function cdmxToday() { const d = cdmxNow(); return d.getFullYear() + '-' + String(d.getMonth()+1).padStart(2,'0') + '-' + String(d.getDate()).padStart(2,'0'); }

const COMMANDS = [
  { command: 'proyectos', description: 'Proyectos activos y avance' },
  { command: 'nomina', description: 'Resumen nomina de la semana' },
  { command: 'hoy', description: 'Quien trabaja hoy y en que' },
  { command: 'entregas', description: 'Proximas entregas de muebles' },
  { command: 'reporte', description: 'Reporte semanal general' },
  { command: 'start', description: 'Menu de comandos' },
];

bot.setMyCommands(COMMANDS).catch(() => {});

// /start
bot.onText(/\/start/, (msg) => {
  if (!isAuth(msg)) return;
  bot.sendMessage(msg.chat.id, [
    'Tiempos PMD Bot',
    '',
    '/proyectos - Proyectos activos y avance',
    '/nomina - Nomina de la semana',
    '/hoy - Quien trabaja hoy',
    '/entregas - Proximas entregas',
    '/reporte - Reporte semanal',
  ].join('\n'));
});

// /proyectos
bot.onText(/\/proyectos/, async (msg) => {
  if (!isAuth(msg)) return;
  try {
    const proyectos = await db.query(`
      SELECT p.*,
        COUNT(DISTINCT m.id) as total_muebles,
        COUNT(DISTINCT CASE WHEN m.fecha_entrega IS NOT NULL AND m.fecha_entrega <= CURDATE() THEN m.id END) as entregados,
        (SELECT COUNT(DISTINCT t.personal_id) FROM tiempos t JOIN muebles m2 ON t.mueble_id=m2.id WHERE m2.proyecto_id=p.id) as personal_asignado
      FROM proyectos p
      LEFT JOIN muebles m ON m.proyecto_id = p.id
      WHERE p.status = 'activo'
      GROUP BY p.id
      ORDER BY p.fecha_inicio
    `);

    if (proyectos.length === 0) {
      bot.sendMessage(msg.chat.id, 'No hay proyectos activos');
      return;
    }

    const lines = ['PROYECTOS ACTIVOS\n'];
    for (const p of proyectos) {
      const inicio = new Date(p.fecha_inicio);
      const fin = new Date(inicio);
      fin.setDate(fin.getDate() + (p.semanas * 7) - 1);
      const hoy = cdmxNow();
      const totalDias = (fin - inicio) / (1000 * 60 * 60 * 24);
      const diasPasados = Math.max(0, (hoy - inicio) / (1000 * 60 * 60 * 24));
      const avance = Math.min(100, Math.round((diasPasados / totalDias) * 100));
      const diasRestantes = Math.max(0, Math.ceil((fin - hoy) / (1000 * 60 * 60 * 24)));

      lines.push(
        (p.abreviacion || p.nombre) + ' - ' + p.cliente,
        '  Muebles: ' + p.total_muebles + ' | Personal: ' + p.personal_asignado,
        '  Tiempo: ' + avance + '% (' + diasRestantes + ' dias restantes)',
        '  Fin: ' + fin.toISOString().slice(0, 10),
        ''
      );
    }
    bot.sendMessage(msg.chat.id, lines.join('\n'));
  } catch (e) {
    bot.sendMessage(msg.chat.id, 'Error: ' + e.message);
  }
});

// /nomina
bot.onText(/\/nomina/, async (msg) => {
  if (!isAuth(msg)) return;
  try {
    // Get current ISO week
    const hoy = cdmxNow();
    const startOfYear = new Date(hoy.getFullYear(), 0, 1);
    const days = Math.floor((hoy - startOfYear) / (1000 * 60 * 60 * 24));
    const semana = Math.ceil((days + startOfYear.getDay() + 1) / 7);

    // Get Monday of current week
    const dayOfWeek = hoy.getDay();
    const lunes = new Date(hoy);
    lunes.setDate(hoy.getDate() - (dayOfWeek === 0 ? 6 : dayOfWeek - 1));
    const lunesStr = lunes.toISOString().slice(0, 10);
    const domingo = new Date(lunes);
    domingo.setDate(lunes.getDate() + 6);
    const domingoStr = domingo.toISOString().slice(0, 10);

    // Payroll summary by team
    const nomina = await db.query(`
      SELECT pe.equipo,
        COUNT(DISTINCT nd.personal_id) as personas,
        COUNT(DISTINCT nd.fecha) as dias_trabajados,
        SUM(CASE WHEN nd.proyecto_id IS NOT NULL THEN 1 ELSE 0 END) as dias_asignados,
        SUM(nd.horas_extra) as total_he,
        GROUP_CONCAT(DISTINCT p.abreviacion ORDER BY p.abreviacion SEPARATOR ', ') as proyectos
      FROM nomina_diaria nd
      JOIN personal pe ON nd.personal_id = pe.id
      LEFT JOIN proyectos p ON nd.proyecto_id = p.id
      WHERE nd.fecha >= ? AND nd.fecha <= ?
      GROUP BY pe.equipo
      ORDER BY pe.equipo
    `, [lunesStr, domingoStr]);

    // Total cost
    const costos = await db.query(`
      SELECT
        SUM(pe.nomina_bruta_semanal / pe.dias_semana) as costo_regular,
        SUM(nd.horas_extra * pe.nomina_bruta_semanal / pe.dias_semana * pe.factor_he) as costo_he
      FROM nomina_diaria nd
      JOIN personal pe ON nd.personal_id = pe.id
      WHERE nd.fecha >= ? AND nd.fecha <= ?
    `, [lunesStr, domingoStr]);

    const lines = ['NOMINA SEMANA ' + semana, lunesStr + ' al ' + domingoStr, ''];

    if (nomina.length === 0) {
      lines.push('Sin registros de nomina esta semana');
    } else {
      for (const n of nomina) {
        lines.push(
          n.equipo + ': ' + n.personas + ' personas',
          '  Proyectos: ' + (n.proyectos || 'N/A'),
          '  HE: ' + (n.total_he || 0) + ' hrs',
          ''
        );
      }
      const reg = costos[0]?.costo_regular || 0;
      const he = costos[0]?.costo_he || 0;
      lines.push(
        'Costo regular: $' + Number(reg).toLocaleString('en', { minimumFractionDigits: 0 }),
        'Costo HE: $' + Number(he).toLocaleString('en', { minimumFractionDigits: 0 }),
        'Total: $' + Number(reg + he).toLocaleString('en', { minimumFractionDigits: 0 })
      );
    }

    bot.sendMessage(msg.chat.id, lines.join('\n'));
  } catch (e) {
    bot.sendMessage(msg.chat.id, 'Error: ' + e.message);
  }
});

// /hoy
bot.onText(/\/hoy/, async (msg) => {
  if (!isAuth(msg)) return;
  try {
    const hoy = cdmxToday();

    // Who has nomina today
    const asignados = await db.query(`
      SELECT pe.nombre, pe.equipo,
        p.abreviacion as proyecto, p.nombre as proyecto_nombre,
        cn.nombre as categoria,
        nd.horas_extra
      FROM nomina_diaria nd
      JOIN personal pe ON nd.personal_id = pe.id
      LEFT JOIN proyectos p ON nd.proyecto_id = p.id
      LEFT JOIN categorias_nomina cn ON nd.categoria_id = cn.id
      WHERE nd.fecha = ?
      ORDER BY pe.equipo, pe.nombre
    `, [hoy]);

    // Who has time entries today
    const trabajando = await db.query(`
      SELECT pe.nombre, pe.equipo, t.proceso, t.horas,
        m.numero, m.descripcion, p.abreviacion
      FROM tiempos t
      JOIN personal pe ON t.personal_id = pe.id
      JOIN muebles m ON t.mueble_id = m.id
      JOIN proyectos p ON m.proyecto_id = p.id
      WHERE t.fecha = ?
      ORDER BY p.abreviacion, t.proceso, pe.nombre
    `, [hoy]);

    const lines = ['HOY ' + hoy, ''];

    if (asignados.length > 0) {
      lines.push('Asignaciones (' + asignados.length + '):');
      let lastEquipo = '';
      for (const a of asignados) {
        if (a.equipo !== lastEquipo) {
          lines.push('\n' + a.equipo + ':');
          lastEquipo = a.equipo;
        }
        const dest = a.proyecto || a.categoria || 'Sin asignar';
        const he = a.horas_extra > 0 ? ' +' + a.horas_extra + 'HE' : '';
        lines.push('  ' + a.nombre.split(' ').slice(0, 2).join(' ') + ' -> ' + dest + he);
      }
    }

    if (trabajando.length > 0) {
      lines.push('\nTiempos registrados (' + trabajando.length + '):');
      let lastProy = '';
      for (const t of trabajando) {
        if (t.abreviacion !== lastProy) {
          lines.push('\n' + t.abreviacion + ':');
          lastProy = t.abreviacion;
        }
        lines.push('  ' + t.nombre.split(' ').slice(0, 2).join(' ') + ' | ' + t.proceso + ' | ' + t.numero + ' | ' + t.horas + 'h');
      }
    }

    if (asignados.length === 0 && trabajando.length === 0) {
      lines.push('Sin registros para hoy');
    }

    bot.sendMessage(msg.chat.id, lines.join('\n'));
  } catch (e) {
    bot.sendMessage(msg.chat.id, 'Error: ' + e.message);
  }
});

// /entregas
bot.onText(/\/entregas/, async (msg) => {
  if (!isAuth(msg)) return;
  try {
    const entregas = await db.query(`
      SELECT m.numero, m.descripcion, m.fecha_entrega, p.abreviacion, p.nombre as proyecto_nombre,
        DATEDIFF(m.fecha_entrega, CURDATE()) as dias_restantes
      FROM muebles m
      JOIN proyectos p ON m.proyecto_id = p.id
      WHERE m.fecha_entrega IS NOT NULL AND m.fecha_entrega >= CURDATE()
        AND p.status = 'activo'
      ORDER BY m.fecha_entrega, p.abreviacion
      LIMIT 30
    `);

    if (entregas.length === 0) {
      bot.sendMessage(msg.chat.id, 'No hay entregas pendientes');
      return;
    }

    // Group by date
    const porFecha = {};
    for (const e of entregas) {
      const fecha = new Date(e.fecha_entrega).toISOString().slice(0, 10);
      if (!porFecha[fecha]) porFecha[fecha] = [];
      porFecha[fecha].push(e);
    }

    const lines = ['PROXIMAS ENTREGAS\n'];
    for (const [fecha, items] of Object.entries(porFecha)) {
      const dias = items[0].dias_restantes;
      const urgente = dias <= 7 ? ' !!!' : dias <= 14 ? ' !' : '';
      lines.push(fecha + ' (' + dias + ' dias)' + urgente);
      for (const e of items) {
        lines.push('  ' + e.abreviacion + ' | ' + e.numero + ' - ' + (e.descripcion || ''));
      }
      lines.push('');
    }

    bot.sendMessage(msg.chat.id, lines.join('\n'));
  } catch (e) {
    bot.sendMessage(msg.chat.id, 'Error: ' + e.message);
  }
});

// /reporte
bot.onText(/\/reporte/, async (msg) => {
  if (!isAuth(msg)) return;
  try {
    // Monday of current week
    const hoy = cdmxNow();
    const dayOfWeek = hoy.getDay();
    const lunes = new Date(hoy);
    lunes.setDate(hoy.getDate() - (dayOfWeek === 0 ? 6 : dayOfWeek - 1));
    const lunesStr = lunes.toISOString().slice(0, 10);
    const domingo = new Date(lunes);
    domingo.setDate(lunes.getDate() + 6);
    const domingoStr = domingo.toISOString().slice(0, 10);

    // Hours by project this week
    const horasProy = await db.query(`
      SELECT p.abreviacion, p.nombre,
        SUM(t.horas) as total_horas,
        COUNT(DISTINCT t.personal_id) as personas,
        COUNT(DISTINCT t.mueble_id) as muebles_trabajados
      FROM tiempos t
      JOIN muebles m ON t.mueble_id = m.id
      JOIN proyectos p ON m.proyecto_id = p.id
      WHERE t.fecha >= ? AND t.fecha <= ?
      GROUP BY p.id
      ORDER BY total_horas DESC
    `, [lunesStr, domingoStr]);

    // Hours by process
    const horasProc = await db.query(`
      SELECT t.proceso, SUM(t.horas) as total_horas, COUNT(DISTINCT t.personal_id) as personas
      FROM tiempos t
      WHERE t.fecha >= ? AND t.fecha <= ?
      GROUP BY t.proceso
      ORDER BY total_horas DESC
    `, [lunesStr, domingoStr]);

    // Upcoming deliveries (next 14 days)
    const entregasProx = await db.query(`
      SELECT COUNT(*) as total,
        SUM(CASE WHEN DATEDIFF(fecha_entrega, CURDATE()) <= 7 THEN 1 ELSE 0 END) as urgentes
      FROM muebles m
      JOIN proyectos p ON m.proyecto_id = p.id
      WHERE m.fecha_entrega IS NOT NULL
        AND m.fecha_entrega >= CURDATE()
        AND m.fecha_entrega <= DATE_ADD(CURDATE(), INTERVAL 14 DAY)
        AND p.status = 'activo'
    `);

    // Total active personnel
    const personalActivo = await db.query(`
      SELECT COUNT(*) as total FROM personal WHERE activo = 1 AND deleted_at IS NULL
    `);

    // Payroll cost
    const costoNomina = await db.query(`
      SELECT
        SUM(pe.nomina_bruta_semanal / pe.dias_semana) as costo_dia,
        SUM(nd.horas_extra * pe.nomina_bruta_semanal / pe.dias_semana * pe.factor_he) as costo_he
      FROM nomina_diaria nd
      JOIN personal pe ON nd.personal_id = pe.id
      WHERE nd.fecha >= ? AND nd.fecha <= ?
    `, [lunesStr, domingoStr]);

    const lines = [
      'REPORTE SEMANAL',
      lunesStr + ' al ' + domingoStr,
      '',
      'Personal activo: ' + personalActivo[0].total,
      ''
    ];

    if (horasProy.length > 0) {
      lines.push('Horas por proyecto:');
      let totalH = 0;
      for (const h of horasProy) {
        lines.push('  ' + (h.abreviacion || h.nombre) + ': ' + h.total_horas + 'h (' + h.personas + ' pers, ' + h.muebles_trabajados + ' muebles)');
        totalH += h.total_horas;
      }
      lines.push('  Total: ' + totalH + 'h', '');
    }

    if (horasProc.length > 0) {
      lines.push('Horas por proceso:');
      for (const h of horasProc) {
        lines.push('  ' + h.proceso + ': ' + h.total_horas + 'h (' + h.personas + ' pers)');
      }
      lines.push('');
    }

    const ent = entregasProx[0] || { total: 0, urgentes: 0 };
    lines.push('Entregas prox 14 dias: ' + ent.total + (ent.urgentes > 0 ? ' (' + ent.urgentes + ' urgentes <7 dias)' : ''));

    const reg = costoNomina[0]?.costo_dia || 0;
    const he = costoNomina[0]?.costo_he || 0;
    if (reg > 0) {
      lines.push('', 'Nomina semana:',
        '  Regular: $' + Number(reg).toLocaleString('en', { minimumFractionDigits: 0 }),
        '  HE: $' + Number(he).toLocaleString('en', { minimumFractionDigits: 0 }),
        '  Total: $' + Number(reg + he).toLocaleString('en', { minimumFractionDigits: 0 })
      );
    }

    bot.sendMessage(msg.chat.id, lines.join('\n'));
  } catch (e) {
    bot.sendMessage(msg.chat.id, 'Error: ' + e.message);
  }
});

console.log('Tiempos PMD Bot activo');
