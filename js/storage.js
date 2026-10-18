// Persistencia local de los productos (IndexedDB guarda Blobs sin problema).
const DB_NAME = 'sanitarios-ar';
const STORE = 'products';

let dbPromise = null;

function openDb() {
  if (!dbPromise) {
    dbPromise = new Promise((resolve, reject) => {
      const req = indexedDB.open(DB_NAME, 1);
      req.onupgradeneeded = () => req.result.createObjectStore(STORE, { keyPath: 'id' });
      req.onsuccess = () => resolve(req.result);
      req.onerror = () => reject(req.error);
    });
  }
  return dbPromise;
}

function run(mode, fn) {
  return openDb().then((db) => new Promise((resolve, reject) => {
    const tx = db.transaction(STORE, mode);
    const req = fn(tx.objectStore(STORE));
    tx.oncomplete = () => resolve(req && req.result);
    tx.onerror = () => reject(tx.error);
  }));
}

export const listProducts = () => run('readonly', (s) => s.getAll()).catch(() => []);
export const saveProduct = (p) => run('readwrite', (s) => s.put(p));
export const deleteProduct = (id) => run('readwrite', (s) => s.delete(id));
