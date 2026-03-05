// utils/slugify.js
// Simple slug generator — matches Laravel's Str::slug()

export function slugify(str = '') {
  return str
    .toLowerCase()
    .trim()
    .replace(/[^\w\s-]/g, '')   // remove special chars
    .replace(/[\s_-]+/g, '-')   // spaces/underscores to hyphens
    .replace(/^-+|-+$/g, '')    // trim leading/trailing hyphens
}