import { create } from 'zustand'
import api from '@/lib/api'
import type { Store, BusinessProfile } from '@/types'

interface ModuleConfigState {
  enabledModules: string[]
  enabledFeatures: string[]
  userPermissions: string[]
  stores: Store[]
  currentStore: Store | null
  businessProfile: BusinessProfile | null
  loaded: boolean

  setConfig: (config: {
    modules: string[]
    features: string[]
    permissions: string[]
    stores: Store[]
    business_profile: BusinessProfile | null
  }) => void

  loadConfig: () => Promise<void>
  refreshConfig: () => Promise<void>
  setStore: (store: Store) => void

  hasModule: (slug: string) => boolean
  hasFeature: (slug: string) => boolean
  hasPermission: (slug: string) => boolean
  can: (module: string, permission: string) => boolean
}

export const useModuleConfigStore = create<ModuleConfigState>((set, get) => ({
  enabledModules: [],
  enabledFeatures: [],
  userPermissions: [],
  stores: [],
  currentStore: null,
  businessProfile: null,
  loaded: false,

  setConfig: (config) => {
    const currentStore = config.stores.length > 0
      ? config.stores.find((s) => s.id === Number(localStorage.getItem('current_store_id')))
      : null
    const store = currentStore ?? config.stores[0] ?? null

    if (store) {
      localStorage.setItem('current_store_id', String(store.id))
    }

    set({
      enabledModules: config.modules,
      enabledFeatures: config.features,
      userPermissions: config.permissions,
      stores: config.stores,
      currentStore: store,
      businessProfile: config.business_profile,
      loaded: true,
    })
  },

  loadConfig: async () => {
    try {
      const response = await api.get('/auth/me')
      const { modules, features, permissions, stores, business_profile } = response.data
      get().setConfig({ modules, features, permissions, stores, business_profile })
    } catch {
      set({ loaded: true })
    }
  },

  refreshConfig: async () => {
    await get().loadConfig()
  },

  setStore: (store) => {
    localStorage.setItem('current_store_id', String(store.id))
    set({ currentStore: store })
  },

  hasModule: (slug) => get().enabledModules.includes(slug),
  hasFeature: (slug) => get().enabledFeatures.includes(slug),
  hasPermission: (slug) => get().userPermissions.includes(slug),
  can: (module, permission) =>
    get().enabledModules.includes(module) && get().userPermissions.includes(permission),
}))
