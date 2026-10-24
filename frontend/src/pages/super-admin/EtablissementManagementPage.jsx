import { useState, useCallback } from 'react';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import { Link } from 'react-router-dom';
import { MdAdd, MdSearch, MdChevronRight, MdRefresh, MdCloudUpload } from 'react-icons/md';
import { listerEtablissements } from '../../api/resources/etablissements';
import NouvelleFaculteModal from '../../components/super-admin/NouvelleFaculteModal';
import ImporterFacultesModal from '../../components/super-admin/ImporterFacultesModal';
import { monogrammeInstitution, tonaliteInstitution } from '../../utils/identiteInstitution';

export default function EtablissementManagementPage() {
  const [search, setSearch] = useState('');
  const [modalOuvert, setModalOuvert] = useState(false);
  const [modalImportOuvert, setModalImportOuvert] = useState(false);
  const queryClient = useQueryClient();

  const etablissementsQuery = useQuery({
    queryKey: ['etablissements'],
    queryFn: ({ signal }) => listerEtablissements(signal),
  });
  const etablissements = etablissementsQuery.data?.data ?? [];
  const loading = etablissementsQuery.isLoading;
  const rafraichir = useCallback(() => queryClient.invalidateQueries({ queryKey: ['etablissements'] }), [queryClient]);

  const filtered = etablissements.filter(
    (e) =>
      e.nom?.toLowerCase().includes(search.toLowerCase()) ||
      e.code?.toLowerCase().includes(search.toLowerCase()) ||
      e.email?.toLowerCase().includes(search.toLowerCase()),
  );

  return (
    <div className="max-w-7xl mx-auto space-y-8">
      {/* Header */}
      <div className="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
        <div>
          <h1 className="titre-page">Facultés & Écoles</h1>
          <p className="sous-titre-page">Le registre des établissements membres de l'UAC</p>
        </div>
        {/* Sur téléphone, l'action principale prend toute la largeur au-dessus
            des actions secondaires : les trois boutons sur une ligne coupaient
            « Importer CSV » et « Ajouter une faculté » en deux. */}
        <div className="flex flex-wrap items-center gap-3">
          <button
            type="button"
            onClick={() => setModalOuvert(true)}
            className="btn-primaire order-first w-full sm:order-last sm:w-auto"
          >
            <MdAdd size={16} aria-hidden="true" />
            Ajouter une faculté
          </button>
          <button
            type="button"
            onClick={() => setModalImportOuvert(true)}
            className="btn-secondaire flex-1 sm:flex-none"
          >
            <MdCloudUpload size={16} aria-hidden="true" />
            Importer CSV
          </button>
          <button
            type="button"
            onClick={rafraichir}
            className="btn-icone"
            title="Rafraîchir"
            aria-label="Rafraîchir la liste"
          >
            <MdRefresh size={18} aria-hidden="true" />
          </button>
        </div>
      </div>

      {/* Search */}
      <div className="relative max-w-md">
        <MdSearch
          className="absolute left-3.5 top-1/2 -translate-y-1/2 text-on-surface-variant"
          size={18}
          aria-hidden="true"
        />
        <input
          type="search"
          aria-label="Rechercher une faculté par nom, code ou email"
          placeholder="Rechercher par nom, code ou email..."
          value={search}
          onChange={(e) => setSearch(e.target.value)}
          className="champ pl-10"
        />
      </div>

      {/* Loading */}
      {loading && (
        <div className="flex items-center justify-center py-16">
          <div className="w-10 h-10 border-4 border-primary border-t-transparent rounded-full animate-spin"></div>
        </div>
      )}

      {/* Registre : chaque faculté porte son propre sceau (monogramme + teinte
          dérivés de son code), pour qu'une liste de lignes identiques devienne
          un vrai répertoire d'institutions distinctes. */}
      {!loading &&
        (filtered.length > 0 ? (
          <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
            {filtered.map((etablissement) => {
              const { fond, texte } = tonaliteInstitution(etablissement.code);
              return (
                <Link
                  key={etablissement.id}
                  to={`/super-admin/etablissements/${etablissement.id}`}
                  className="group flex flex-col gap-4 rounded-xxl border border-outline-variant/15 bg-surface-container-lowest p-5 transition-all hover:-translate-y-0.5 hover:shadow-[0_12px_28px_rgba(1,21,73,0.08)]"
                >
                  <div className="flex items-start justify-between">
                    <div
                      className="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl font-headline text-base font-bold"
                      style={{ backgroundColor: fond, color: texte }}
                      aria-hidden="true"
                    >
                      {monogrammeInstitution(etablissement.code)}
                    </div>
                    {etablissement.actif ? (
                      <span className="rounded-full bg-statut-succes-fond px-2 py-0.5 text-[11px] font-semibold text-statut-succes">
                        Actif
                      </span>
                    ) : (
                      <span className="rounded-full bg-surface-container-high px-2 py-0.5 text-[11px] font-semibold text-on-surface-variant">
                        Inactif
                      </span>
                    )}
                  </div>

                  <div className="min-w-0">
                    <p className="truncate font-headline text-base font-bold text-primary">{etablissement.nom}</p>
                    <p className="mt-0.5 truncate text-xs text-on-surface-variant">{etablissement.email}</p>
                  </div>

                  <div className="mt-auto flex items-center gap-4 border-t border-outline-variant/10 pt-3 text-xs text-on-surface-variant">
                    <span className="font-mono font-semibold text-primary">{etablissement.code}</span>
                    <span>
                      {etablissement.filieres_count ?? 0} filière{(etablissement.filieres_count ?? 0) > 1 ? 's' : ''}
                    </span>
                    <span>
                      {etablissement.users_count ?? 0} admin{(etablissement.users_count ?? 0) > 1 ? 's' : ''}
                    </span>
                    <MdChevronRight
                      className="ml-auto shrink-0 text-on-surface-variant transition-transform group-hover:translate-x-0.5"
                      size={16}
                    />
                  </div>
                </Link>
              );
            })}
          </div>
        ) : (
          <div className="rounded-xxl border border-dashed border-outline-variant/30 py-16 text-center">
            <div className="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-2xl border-2 border-dashed border-outline-variant/40 font-headline text-lg font-bold text-outline-variant">
              ?
            </div>
            <p className="text-sm text-on-surface-variant mb-2">Aucune faculté trouvée</p>
            {search ? (
              <p className="text-xs text-on-surface-variant">Essayez de modifier votre recherche</p>
            ) : (
              <button onClick={() => setModalOuvert(true)} className="text-sm text-primary hover:underline font-medium">
                Ajouter la première faculté
              </button>
            )}
          </div>
        ))}

      <NouvelleFaculteModal isOpen={modalOuvert} onClose={() => setModalOuvert(false)} onCreated={rafraichir} />
      <ImporterFacultesModal
        isOpen={modalImportOuvert}
        onClose={() => setModalImportOuvert(false)}
        onImported={rafraichir}
      />
    </div>
  );
}
