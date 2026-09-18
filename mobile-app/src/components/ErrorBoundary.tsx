import { Component, type ErrorInfo, type ReactNode } from 'react';
import { View, Text, Pressable } from 'react-native';

interface ErrorBoundaryProps {
  children: ReactNode;
  /** Remplace l'écran de repli par défaut, pour les tests ou un écran dédié. */
  fallback?: (erreur: Error, reessayer: () => void) => ReactNode;
}

interface ErrorBoundaryState {
  erreur: Error | null;
}

/**
 * P3.6 — barrière d'erreur de la racine de l'application.
 *
 * Sans elle, la moindre exception levée pendant le rendu démontait tout l'arbre
 * React : l'étudiant se retrouvait devant un écran BLANC, sans message ni moyen
 * de repartir, et n'avait d'autre choix que de tuer l'application. Le repli
 * ci-dessous nomme la panne et propose de recharger l'écran.
 */
export class ErrorBoundary extends Component<ErrorBoundaryProps, ErrorBoundaryState> {
  state: ErrorBoundaryState = { erreur: null };

  static getDerivedStateFromError(erreur: Error): ErrorBoundaryState {
    return { erreur };
  }

  componentDidCatch(erreur: Error, infos: ErrorInfo) {
    // La trace reste la seule piste exploitable sur un appareil d'étudiant :
    // on la conserve dans la console, lisible via les journaux de l'appareil.
    console.error('[ErrorBoundary]', erreur, infos.componentStack);
  }

  reessayer = () => {
    this.setState({ erreur: null });
  };

  render() {
    const { erreur } = this.state;
    if (!erreur) return this.props.children;

    if (this.props.fallback) return this.props.fallback(erreur, this.reessayer);

    return (
      <View className="flex-1 items-center justify-center bg-background px-6">
        <Text className="text-center font-headline text-xl text-on-surface">
          L&apos;application a rencontré un problème
        </Text>
        <Text className="mt-3 text-center text-base text-on-surface-variant">
          Rien n&apos;a été perdu. Réessayez, et si l&apos;écran reste bloqué,
          fermez puis rouvrez l&apos;application.
        </Text>
        <Text className="mt-4 text-center text-xs text-on-surface-variant">
          {erreur.message}
        </Text>
        <Pressable
          accessibilityRole="button"
          onPress={this.reessayer}
          className="mt-8 rounded-lg bg-primary px-6 py-3"
        >
          <Text className="text-base text-on-primary">Réessayer</Text>
        </Pressable>
      </View>
    );
  }
}
