import { View, Text, FlatList } from 'react-native';
import { Card } from '../../src/components/ui/Card';
import { LoadingSpinner } from '../../src/components/ui/LoadingSpinner';
import { useQuery } from '@tanstack/react-query';
import apiClient from '../../src/api/client';
import type { Presence } from '../../src/types';
import { Clock, CheckCircle, XCircle, AlertTriangle } from 'lucide-react-native';

function StatusIcon({ statut }: { statut: Presence['statut'] }) {
  switch (statut) {
    case 'valide':
      return <CheckCircle size={20} color="#006d43" />;
    case 'rejete':
      return <XCircle size={20} color="#ba1a1a" />;
    case 'suspect':
      return <AlertTriangle size={20} color="#ffb700" />;
    default:
      return <Clock size={20} color="#757680" />;
  }
}

function PresenceItem({ presence }: { presence: Presence }) {
  return (
    <Card className="mb-3">
      <View className="flex-row items-center gap-x-3">
        <StatusIcon statut={presence.statut} />
        <View className="flex-1">
          <Text className="font-semibold text-on-surface">
            {presence.evenement?.titre ?? presence.evenement?.nom ?? 'Cours'}
          </Text>
          <Text className="mt-1 text-sm text-on-surface-variant">
            {new Date(presence.heure_scan).toLocaleString('fr-FR', {
              dateStyle: 'medium',
              timeStyle: 'short',
            })}
          </Text>
        </View>
        <Text
          className={`text-xs font-medium ${
            presence.statut === 'valide'
              ? 'text-secondary'
              : presence.statut === 'rejete'
                ? 'text-error'
                : 'text-on-surface-variant'
          }`}
        >
          {presence.statut.charAt(0).toUpperCase() + presence.statut.slice(1)}
        </Text>
      </View>
    </Card>
  );
}

export default function HistoryTab() {
  const { data, isLoading } = useQuery({
    queryKey: ['my-presences'],
    queryFn: async () => {
      const { data: body } = await apiClient.get('/presence/my-history');
      // Laravel paginator : { data: [...], current_page, ... }
      return (body as Record<string, unknown>).data ?? body ?? [];
    },
  });

  if (isLoading) return <LoadingSpinner message="Chargement de l'historique..." />;

  const presences: Presence[] = data ?? [];

  if (presences.length === 0) {
    return (
      <View className="flex-1 items-center justify-center bg-background px-6">
        <Clock size={48} color="#757680" />
        <Text className="mt-4 text-center text-lg text-on-surface-variant">
          Aucun scan enregistré.{'\n'}
          Scannez un QR code pour valider votre première présence !
        </Text>
      </View>
    );
  }

  return (
    <FlatList
      className="flex-1 bg-background px-4 pt-4"
      data={presences}
      keyExtractor={(item) => String(item.id)}
      renderItem={({ item }) => <PresenceItem presence={item} />}
      contentContainerClassName="pb-6"
    />
  );
}