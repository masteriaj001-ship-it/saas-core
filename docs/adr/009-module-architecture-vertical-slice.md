# ADR-009: Vertical Slice Architecture

**Estado:** Rejected
**Fecha:** 2026-08-20

## Contexto

Modular monolith vigente (app/Modules/*) con R-01 como perímetro.
Suite: 404 tests / 984 assertions. Tenants activos: 0.

## Decisión

No migrar a Vertical Slice en esta etapa.

## Razones

- El perímetro de módulos ya existe y está enforced por R-01
- Costo de refactor alto sin retorno medible con 0 tenants
- Vertical Slice resuelve crecimiento sin límite — problema que aún no tenemos

## Trigger para revisitar

+5 tenants activos en producción
O monolith supera ~10 módulos / >30 min build CI

## Consecuencias

Se mantiene modular monolith. Crecimiento se controla con reglas de límite de módulos.