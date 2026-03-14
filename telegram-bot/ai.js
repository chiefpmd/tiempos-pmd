const Anthropic = require('@anthropic-ai/sdk');
const db = require('./db');

const client = new Anthropic({ apiKey: process.env.ANTHROPIC_API_KEY });

const DB_SCHEMA = `
Tables in tiempos_pmd (MySQL):

proyectos: id, nombre, abreviacion(varchar10), cliente, fecha_inicio(date), semanas(int), status(enum: activo/completado/pausado)
muebles: id, proyecto_id(FK), numero, descripcion, costo_mueble(decimal), fecha_entrega(date)
personal: id, nombre, equipo(enum: Carpintería/Barniz/Instalación/Vidrio/Eléctrico/Mantenimiento/Herrero/Armado/Tapicero), color_hex, activo(bool), es_lider(bool), lider_id(FK), clave_empleado, nomina_bruta_semanal(decimal), dias_semana(int), factor_he(decimal), deleted_at
tiempos: id, mueble_id(FK), proceso(enum: Carpintería/Barniz/Instalación), personal_id(FK), fecha(date), horas(decimal 4,1)
nomina_diaria: id, personal_id(FK), fecha(date), semana(int), proyecto_id(FK), mueble_id(FK), categoria_id(FK), horas_extra(decimal 4,1), proyecto_he_id(FK)
categorias_nomina: id, nombre, activa(bool)
equipo_diario: id, personal_id(FK), lider_id(FK), fecha(date)
gantt_anual: id, proyecto_id(FK unique), fecha_inicio(date), fecha_fin(date)
proyecto_materiales: id, proyecto_id(FK), tipo(enum: pedido/entrega), fecha(date)
dias_festivos: id, fecha(date unique), nombre
`;

const SYSTEM_PROMPT = `Eres el asistente de PMD, una empresa de muebles en CDMX. Respondes consultas sobre proyectos, personal, tiempos, nómina y entregas.

${DB_SCHEMA}

REGLAS:
- Genera queries SELECT solamente. NUNCA INSERT, UPDATE, DELETE, DROP, ALTER ni nada que modifique datos.
- Usa la tool "run_query" para consultar la BD. Puedes hacer múltiples queries si necesitas.
- Para personal activo filtra: activo=1 AND deleted_at IS NULL
- Para proyectos activos filtra: status='activo'
- Fechas en formato YYYY-MM-DD. Hoy es: ${new Date().toLocaleDateString('en-CA', { timeZone: 'America/Mexico_City' })}
- Responde en español, conciso y directo.
- Si no puedes responder con la BD, dilo claramente.
- Formatea números con comas para miles y $ para pesos.`;

const tools = [
  {
    name: 'run_query',
    description: 'Ejecuta un query SELECT en la base de datos MySQL de tiempos_pmd',
    input_schema: {
      type: 'object',
      properties: {
        sql: { type: 'string', description: 'Query SELECT a ejecutar' }
      },
      required: ['sql']
    }
  }
];

async function askClaude(question) {
  const messages = [{ role: 'user', content: question }];

  // Loop for tool use
  for (let i = 0; i < 5; i++) {
    const response = await client.messages.create({
      model: 'claude-sonnet-4-20250514',
      max_tokens: 1024,
      system: SYSTEM_PROMPT,
      tools,
      messages,
    });

    // Check if there are tool calls
    const toolUses = response.content.filter(b => b.type === 'tool_use');

    if (toolUses.length === 0) {
      // Final text response
      const text = response.content.filter(b => b.type === 'text').map(b => b.text).join('');
      return text || 'Sin respuesta';
    }

    // Add assistant response
    messages.push({ role: 'assistant', content: response.content });

    // Execute each tool call
    const toolResults = [];
    for (const tool of toolUses) {
      if (tool.name === 'run_query') {
        const sql = tool.input.sql.trim().toUpperCase();
        // Safety check: only SELECT
        if (!sql.startsWith('SELECT') && !sql.startsWith('SHOW') && !sql.startsWith('DESCRIBE')) {
          toolResults.push({
            type: 'tool_result',
            tool_use_id: tool.id,
            content: 'Error: Solo se permiten queries SELECT',
          });
          continue;
        }
        try {
          const rows = await db.query(tool.input.sql);
          const result = JSON.stringify(rows.slice(0, 50)); // Limit rows
          toolResults.push({
            type: 'tool_result',
            tool_use_id: tool.id,
            content: result,
          });
        } catch (e) {
          toolResults.push({
            type: 'tool_result',
            tool_use_id: tool.id,
            content: 'Error SQL: ' + e.message,
          });
        }
      }
    }

    messages.push({ role: 'user', content: toolResults });
  }

  return 'Se excedió el límite de consultas, intenta una pregunta más específica.';
}

module.exports = { askClaude };
