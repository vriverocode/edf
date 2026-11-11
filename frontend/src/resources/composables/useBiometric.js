import { Capacitor } from '@capacitor/core'
import { NativeBiometric } from '@capgo/capacitor-native-biometric'
import storage from '@/services/storage'

export const BIOMETRIC_SERVER = 'com.edfteam.edfapp'
export const BIOMETRIC_ENABLED_KEY = 'biometric_enabled'
export const BIOMETRIC_EMAIL_KEY = 'biometric_email'
export const BIOMETRIC_USERNAME_KEY = 'biometric_username'
export const BIOMETRIC_PREFERENCE_KEY = 'biometric_preference'

const BIOMETRIC_CANCEL_CODES = [11, 15, 16, 17]

const isNativePlatform = () => Capacitor.isNativePlatform()

const ensureNativePlatform = () => {
  if (!isNativePlatform()) {
    throw new Error('La autenticación biométrica solo está disponible en la app móvil')
  }
}

export const isBiometricCancel = (error) => {
  return BIOMETRIC_CANCEL_CODES.includes(Number(error?.code))
}

export const getBiometricEmail = () => {
  return storage.getItem(BIOMETRIC_EMAIL_KEY)
}

export const getBiometricUsername = () => {
  return storage.getItem(BIOMETRIC_USERNAME_KEY) || storage.getItem('rememberUser') || ''
}

export const setBiometricUsername = (username) => {
  if (username) {
    storage.setItem(BIOMETRIC_USERNAME_KEY, username)
  } else {
    storage.deleteItem(BIOMETRIC_USERNAME_KEY)
  }
}

export const setBiometricPreference = (enabled) => {
  if (enabled) {
    storage.setItem(BIOMETRIC_PREFERENCE_KEY, 'true')
  } else {
    storage.deleteItem(BIOMETRIC_PREFERENCE_KEY)
  }
}

export const hasBiometricEnabled = () => {
  return storage.getItem(BIOMETRIC_ENABLED_KEY) === 'true' && !!getBiometricEmail()
}

export const hasBiometricPreference = () => {
  return storage.getItem(BIOMETRIC_PREFERENCE_KEY) === 'true' || hasBiometricEnabled()
}

export const enableBiometric = (email, username = null) => {
  storage.setItem(BIOMETRIC_ENABLED_KEY, 'true')
  storage.setItem(BIOMETRIC_EMAIL_KEY, email)
  setBiometricPreference(true)

  if (username !== null) {
    setBiometricUsername(username)
  }

  storage.deleteItem('initBiometric')
}

export const clearBiometricEnrollment = () => {
  storage.deleteItem(BIOMETRIC_ENABLED_KEY)
  storage.deleteItem(BIOMETRIC_EMAIL_KEY)
  storage.deleteItem('initBiometric')
}

export const disableBiometric = () => {
  clearBiometricEnrollment()
  setBiometricPreference(false)
  setBiometricUsername('')
}

export const isBiometricAvailable = async () => {
  if (!isNativePlatform()) return false

  try {
    const result = await NativeBiometric.isAvailable({ useFallback: false })
    return result?.isAvailable === true
  } catch (error) {
    console.error('Biometric availability error:', error)
    return false
  }
}

export const promptBiometric = async (options = {}) => {
  ensureNativePlatform()

  await NativeBiometric.verifyIdentity({
    reason: 'Usa tu huella para iniciar sesión en PACIFIK',
    title: 'Autenticación biométrica',
    subtitle: 'Acceso seguro a PACIFIK',
    description: 'Confirma tu identidad para continuar',
    negativeButtonText: 'Usar contraseña',
    maxAttempts: 3,
    useFallback: false,
    ...options
  })
}

export const saveStoredBiometricToken = async ({ email, token }) => {
  ensureNativePlatform()

  if (!email || !token) {
    throw new Error('No se recibieron las credenciales para guardar')
  }

  await NativeBiometric.setCredentials({
    username: email,
    password: token,
    server: BIOMETRIC_SERVER
  })
}

export const getStoredBiometricToken = async () => {
  ensureNativePlatform()

  const credentials = await NativeBiometric.getCredentials({
    server: BIOMETRIC_SERVER
  })

  if (!credentials?.password) {
    throw new Error('No hay un token biométrico guardado')
  }

  return {
    email: credentials.username,
    token: credentials.password
  }
}

export const deleteStoredBiometricToken = async () => {
  if (!isNativePlatform()) return false

  try {
    await NativeBiometric.deleteCredentials({ server: BIOMETRIC_SERVER })
    return true
  } catch (error) {
    console.error('Biometric credentials delete error:', error)
    return false
  }
}

export function useBiometric() {
  return {
    isSupported: isNativePlatform(),
    isAvailable: isBiometricAvailable,
    prompt: promptBiometric,
    saveCredentials: saveStoredBiometricToken,
    getCredentials: getStoredBiometricToken,
    deleteCredentials: deleteStoredBiometricToken,
    hasEnabled: hasBiometricEnabled,
    hasPreference: hasBiometricPreference,
    getEmail: getBiometricEmail,
    getUsername: getBiometricUsername,
    setUsername: setBiometricUsername,
    enable: enableBiometric,
    clearEnrollment: clearBiometricEnrollment,
    disable: disableBiometric,
    isCancel: isBiometricCancel
  }
}
