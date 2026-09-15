import { useCallback, useEffect, useRef, useState } from 'react';
import { View, Text, ScrollView, Share, RefreshControl } from 'react-native';
import { SvgXml } from 'react-native-svg';
import { QrCode as QrCodeIcon, Clock, MapPin, RefreshCw, CheckCircle2 } from 'lucide-react-native';
import { Card } from '../../src/components/ui/Card';
import { Button } from '../../src/components/ui/Button';
import { LoadingSpinner } from '../../src/components/ui/LoadingSpinner';
import apiClient from '../../src/api/client';
import { useScan } from '../../src/hooks/useScan';
import { showToast } from '../../src/utils/toast-config';

/**
 * Onglet réservé à l'étudiant responsable (délégué) de la promotion.
 *
 * Le délégué affiche et partage le QR Code que le serveur a produit ; il ne peut
 * pas en déclencher la génération. L'écran interroge donc uniquement un endpoint
 * de lecture, et se rafraîchit plus vite que la rotation du token pour ne jamais
 * afficher un code périmé.
 */

/** Rafraîchissement plus court que la rotation serveur (60 s nominales). */
const INTERVALLE_RAFRAICHISSEMENT_MS = 20_000;

interface CreneauQr {
  token: string;
  svg: string;
  url: string;
  expires_in: number;
  evenement: {
    id: number;
    cours: string | null;
    code: string | null;
    salle: string | null;
    heure_debut: string;
    heure_fin: string;
    ferme_a: string;
  };
}

export default function QrCodeTab() {
  const [creneau, setCreneau] = useState<CreneauQr | null>(null);
  const [message, setMessage] = useState<string | null>(null);
  const [chargement, setChargement] = useState(true);
  const [rafraichissement, setRafraichissement] = useState(false);
  const [secondes, setSecondes] = useState(0);
  const monte = useRef(true);

  // Le délégué ne peut pas scanner l'écran qu'il présente lui-même. Il détient
  // déjà le token : sa présence s'enregistre par le même chemin que celui d'un
  // scan (mêmes contrôles GPS, Wi-Fi, empreinte et challenge côté serveur), la
  // caméra n'étant qu'un moyen de transporter le token.
  const { submitScan, scanning } = useScan();

  // Rattaché à l'identifiant de la séance : la confirmation ne doit pas survivre
  // au passage au cours suivant, que le rafraîchissement périodique amène.
  const [valideePourEvenement, setValideePourEvenement] = useState<number | null>(null);
  const [heureEnregistrement, setHeureEnregistrement] = useState<string | null>(null);

  const charger = useCallback(async () => {
    try {
      const { data } = await apiClient.get('/student/qrcode/current');
      if (!monte.current) return;
      setCreneau(data.data);
      setSecondes(data.data?.expires_in ?? 0);
      setMessage(null);
    } catch (error: any) {
      if (!monte.current) return;
      setCreneau(null);
      // 404 et 403 portent un message explicite du serveur : on l'affiche tel
      // quel plutôt qu'une formule générique.
      setMessage(
        error?.response?.data?.message
          ?? "Impossible de récupérer le QR Code du cours. Vérifiez votre connexion.",
      );
    } finally {
      if (monte.current) {
        setChargement(false);
        setRafraichissement(false);
      }
    }
  }, []);

  useEffect(() => {
    monte.current = true;
    charger();

    const minuterie = setInterval(charger, INTERVALLE_RAFRAICHISSEMENT_MS);

    return () => {
      monte.current = false;
      clearInterval(minuterie);
    };
  }, [charger]);

  // Compte à rebours local, purement indicatif : la source de vérité reste
  // l'expiration renvoyée par le serveur à chaque rafraîchissement.
  useEffect(() => {
    if (!creneau) return;

    const tic = setInterval(() => {
      setSecondes((s) => (s > 0 ? s - 1 : 0));
    }, 1000);

    return () => clearInterval(tic);
  }, [creneau]);

  async function validerMaPresence() {
    if (!creneau) return;

    try {
      const resultat = await submitScan(creneau.token);
      if (!monte.current) return;

      setValideePourEvenement(creneau.evenement.id);
      // Heure du serveur, pas celle de l'appareil : c'est elle qui figure dans
      // le registre de présence.
      setHeureEnregistrement(resultat.data?.heure?.slice(0, 5) ?? null);

      // Un scan réussi fait tourner le token côté serveur (CDC 9.2.1) : sans ce
      // rechargement, le délégué continuerait de présenter un code périmé aux
      // étudiants qui n'ont pas encore scanné.
      charger();
    } catch (error: any) {
      if (!monte.current) return;

      // 409 : la présence était déjà enregistrée. Pour le délégué, l'objectif
      // est atteint — on bascule sur la confirmation au lieu de laisser un
      // bouton qui semble échouer. useScan a déjà informé l'utilisateur.
      if (error?.response?.status === 409) {
        setValideePourEvenement(creneau.evenement.id);
        setHeureEnregistrement(null);
      }
    }
  }

  async function partager() {
    if (!creneau) return;
    try {
      await Share.share({
        message: `Présence — ${creneau.evenement.cours ?? 'cours'} : ${creneau.url}`,
      });
    } catch {
      showToast('error', 'Partage impossible', 'Réessayez dans un instant.');
    }
  }

  if (chargement) {
    return (
      <View className="flex-1 items-center justify-center bg-background">
        <LoadingSpinner />
      </View>
    );
  }

  return (
    <ScrollView
      className="flex-1 bg-background px-4 pt-4"
      refreshControl={
        <RefreshControl
          refreshing={rafraichissement}
          onRefresh={() => {
            setRafraichissement(true);
            charger();
          }}
        />
      }
    >
      {!creneau ? (
        <Card className="items-center py-8">
          <View className="mb-4 h-16 w-16 items-center justify-center rounded-full bg-surface-container-high">
            <QrCodeIcon size={32} color="#757680" />
          </View>
          <Text className="mb-2 text-center font-headline text-lg text-on-surface">
            Aucun QR Code à afficher
          </Text>
          <Text className="text-center text-sm text-on-surface-variant">{message}</Text>
          <Button
            className="mt-5"
            onPress={() => {
              setRafraichissement(true);
              charger();
            }}
          >
            Réessayer
          </Button>
        </Card>
      ) : (
        <>
          <Card className="mb-4">
            <Text className="font-headline text-lg text-on-surface">
              {creneau.evenement.cours ?? 'Cours'}
            </Text>
            {creneau.evenement.code && (
              <Text className="mt-0.5 text-xs text-on-surface-variant">{creneau.evenement.code}</Text>
            )}
            <View className="mt-3 gap-y-2">
              <View className="flex-row items-center gap-x-2">
                <Clock size={16} color="#757680" />
                <Text className="text-sm text-on-surface">
                  {creneau.evenement.heure_debut} – {creneau.evenement.heure_fin}
                </Text>
              </View>
              {creneau.evenement.salle && (
                <View className="flex-row items-center gap-x-2">
                  <MapPin size={16} color="#757680" />
                  <Text className="text-sm text-on-surface">{creneau.evenement.salle}</Text>
                </View>
              )}
            </View>
          </Card>

          <Card className="mb-4 items-center">
            <View className="rounded-2xl bg-white p-3">
              <SvgXml xml={creneau.svg} width={240} height={240} />
            </View>

            <View className="mt-4 flex-row items-center gap-x-2">
              <RefreshCw size={14} color="#757680" />
              <Text className="text-xs text-on-surface-variant">
                {secondes > 0
                  ? `Nouveau code dans ${secondes} s`
                  : 'Renouvellement en cours…'}
              </Text>
            </View>
            <Text className="mt-1 text-xs text-on-surface-variant">
              Présence ouverte jusqu'à {creneau.evenement.ferme_a}
            </Text>
          </Card>

          {valideePourEvenement === creneau.evenement.id ? (
            <Card className="mb-4 flex-row items-center gap-x-3">
              <CheckCircle2 size={22} color="#008751" />
              <View className="flex-1">
                <Text className="font-headline text-sm text-on-surface">
                  Votre présence est enregistrée
                </Text>
                <Text className="mt-0.5 text-xs text-on-surface-variant">
                  {heureEnregistrement
                    ? `à ${heureEnregistrement} — ${creneau.evenement.cours ?? 'ce cours'}`
                    : `pour ${creneau.evenement.cours ?? 'ce cours'}`}
                </Text>
              </View>
            </Card>
          ) : (
            <Button onPress={validerMaPresence} loading={scanning} className="mb-4">
              Valider ma présence
            </Button>
          )}

          <Button onPress={partager} variant="outline" className="mb-8">
            Partager le lien de présence
          </Button>
        </>
      )}
    </ScrollView>
  );
}
