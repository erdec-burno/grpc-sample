const { Pool } = require('pg');

const pool = new Pool({
  host: process.env.DB_HOST || 'localhost',
  port: Number(process.env.DB_PORT || 5432),
  database: process.env.DB_NAME || 'laravel',
  user: process.env.DB_USER || 'laravel',
  password: process.env.DB_PASSWORD || 'laravel',
});

async function initSchema() {
  await pool.query(`
    CREATE TABLE IF NOT EXISTS grpc_users (
      id INTEGER GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
      name VARCHAR(255) NOT NULL,
      email VARCHAR(255) NOT NULL UNIQUE,
      status INTEGER NOT NULL DEFAULT 1,
      created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
      updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
    )
  `);
}

async function getUserById(id) {
  const result = await pool.query(
    `SELECT id, name, email, status
       FROM grpc_users
      WHERE id = $1`,
    [id]
  );

  return result.rows[0] || null;
}

async function createUser({ name, email, status = 1 }) {
  const result = await pool.query(
    `INSERT INTO grpc_users (name, email, status)
     VALUES ($1, $2, $3)
     RETURNING id, name, email, status`,
    [name, email, status]
  );

  return result.rows[0];
}

async function ping() {
  await pool.query('SELECT 1');
}

async function closePool() {
  await pool.end();
}

module.exports = {
  initSchema,
  getUserById,
  createUser,
  ping,
  closePool,
};
