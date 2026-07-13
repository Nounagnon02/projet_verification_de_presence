import * as SecureStore from 'expo-secure-store';
import AsyncStorage from '@react-native-async-storage/async-storage';
import { CONFIG } from '../constants/config';
import type { ApiUser } from '../types';

// ─── Token Bearer (SecureStore — chiffré) ───

export async function getToken(): Promise<string | null> {
  try {
    return await SecureStore.getItemAsync(CONFIG.TOKEN_KEY);
  } catch {
    return null;
  }
}

export async function setToken(token: string): Promise<void> {
  await SecureStore.setItemAsync(CONFIG.TOKEN_KEY, token);
}

export async function deleteToken(): Promise<void> {
  await SecureStore.deleteItemAsync(CONFIG.TOKEN_KEY);
}

// ─── Cache utilisateur (AsyncStorage — non sensible) ───

export async function getCachedUser(): Promise<ApiUser | null> {
  try {
    const raw = await AsyncStorage.getItem(CONFIG.USER_KEY);
    return raw ? (JSON.parse(raw) as ApiUser) : null;
  } catch {
    return null;
  }
}

export async function setCachedUser(user: ApiUser): Promise<void> {
  await AsyncStorage.setItem(CONFIG.USER_KEY, JSON.stringify(user));
}

// ─── Nettoyage complet ───

export async function clearAuth(): Promise<void> {
  await Promise.all([
    SecureStore.deleteItemAsync(CONFIG.TOKEN_KEY),
    AsyncStorage.removeItem(CONFIG.USER_KEY),
  ]);
}