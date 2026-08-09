"use server";

import { revalidatePath } from "next/cache";
import { db } from "@/lib/db";
import { requireOperator } from "@/lib/require-operator";

export async function createProduct(formData: FormData) {
  await requireOperator();

  const name = String(formData.get("name") ?? "").trim();
  // Normalised hard, because this string is sent by every install on every
  // request forever. A stray capital or space here would be permanent.
  const slug = String(formData.get("slug") ?? "")
    .trim()
    .toLowerCase()
    .replace(/[^a-z0-9-]/g, "-")
    .replace(/-+/g, "-")
    .replace(/^-|-$/g, "");

  if (!name || !slug) return;

  // Reusing a slug would attach new sales to an existing product's licences.
  // Fail quietly rather than merging two products together.
  const existing = await db.product.findUnique({ where: { slug }, select: { id: true } });
  if (existing) return;

  await db.product.create({
    data: {
      name,
      slug,
      description: String(formData.get("description") ?? "").trim(),
      summary: String(formData.get("summary") ?? "").trim(),
    },
  });

  revalidatePath("/products");
  revalidatePath("/releases");
  revalidatePath("/api/public/products");
}

export async function toggleProductActive(formData: FormData) {
  await requireOperator();

  const id = String(formData.get("id") ?? "");
  const product = await db.product.findUnique({ where: { id }, select: { active: true } });
  if (!product) return;

  await db.product.update({ where: { id }, data: { active: !product.active } });
  revalidatePath("/products");
}
