import { useEffect, useState } from 'react';
import { motion } from 'motion/react';
import { KeyRound, UserPlus } from 'lucide-react';
import { useNavigate, useParams } from 'react-router-dom';
import api from '../api';
import type { AuthState } from '../App';

interface AcceptInvitationProps {
  onAccepted: (payload: Partial<AuthState>) => void;
}

interface InvitationDetails {
  email: string;
  expires_at: string | null;
}

export default function AcceptInvitation({ onAccepted }: AcceptInvitationProps) {
  const navigate = useNavigate();
  const { token = '' } = useParams();
  const [loading, setLoading] = useState(true);
  const [submitting, setSubmitting] = useState(false);
  const [details, setDetails] = useState<InvitationDetails | null>(null);
  const [name, setName] = useState('');
  const [password, setPassword] = useState('');
  const [passwordConfirmation, setPasswordConfirmation] = useState('');
  const [error, setError] = useState('');

  useEffect(() => {
    void fetchInvitation();
  }, [token]);

  const fetchInvitation = async () => {
    setLoading(true);
    setError('');

    try {
      const response = await api.get<InvitationDetails>(`/invitations/${token}`);
      setDetails(response.data);
    } catch (err: any) {
      setError(err.response?.data?.error || 'Convite invalido ou expirado.');
    } finally {
      setLoading(false);
    }
  };

  const handleSubmit = async (event: React.FormEvent) => {
    event.preventDefault();
    setSubmitting(true);
    setError('');

    try {
      const response = await api.post('/invitations/accept', {
        token,
        name,
        password,
        password_confirmation: passwordConfirmation,
      });

      onAccepted(response.data);
      navigate('/settings/integration');
    } catch (err: any) {
      setError(err.response?.data?.message || err.response?.data?.error || 'Nao foi possivel concluir o cadastro.');
    } finally {
      setSubmitting(false);
    }
  };

  return (
    <div className="flex min-h-screen items-center justify-center bg-slate-50 px-4 py-8">
      <motion.div
        initial={{ opacity: 0, y: 12 }}
        animate={{ opacity: 1, y: 0 }}
        className="w-full max-w-xl rounded-3xl border border-slate-100 bg-white p-8 shadow-xl shadow-slate-200/50 sm:p-10"
      >
        <div className="mb-8 flex items-center gap-4">
          <div className="flex h-16 w-16 items-center justify-center rounded-2xl bg-primary/10 text-primary">
            <UserPlus size={30} />
          </div>
          <div>
            <h1 className="text-2xl font-bold text-slate-900">Criar conta no WooPack</h1>
            <p className="mt-1 text-sm text-slate-500">Seu acesso foi liberado por convite.</p>
          </div>
        </div>

        {loading ? (
          <div className="flex h-40 items-center justify-center">
            <div className="h-10 w-10 animate-spin rounded-full border-4 border-primary border-t-transparent" />
          </div>
        ) : error && !details ? (
          <div className="rounded-2xl border border-red-100 bg-red-50 px-5 py-4 text-sm text-red-600">
            {error}
          </div>
        ) : (
          <form onSubmit={handleSubmit} className="space-y-6">
            <div className="rounded-2xl bg-slate-50 px-5 py-4">
              <div className="text-xs font-bold uppercase tracking-widest text-slate-400">Convite para</div>
              <div className="mt-1 text-base font-semibold text-slate-900">{details?.email}</div>
              {details?.expires_at && (
                <div className="mt-2 text-xs text-slate-500">
                  Expira em {new Date(details.expires_at).toLocaleString('pt-BR')}
                </div>
              )}
            </div>

            <div className="space-y-2">
              <label className="text-sm font-medium text-slate-700">Nome</label>
              <input
                type="text"
                value={name}
                onChange={(event) => setName(event.target.value)}
                placeholder="Seu nome"
                className="input-modern"
                required
              />
            </div>

            <div className="grid gap-6 sm:grid-cols-2">
              <div className="space-y-2">
                <label className="text-sm font-medium text-slate-700">Senha</label>
                <div className="relative">
                  <KeyRound size={18} className="absolute left-4 top-1/2 -translate-y-1/2 text-slate-400" />
                  <input
                    type="password"
                    value={password}
                    onChange={(event) => setPassword(event.target.value)}
                    placeholder="Minimo 8 caracteres"
                    className="input-modern input-modern-icon"
                    required
                  />
                </div>
              </div>

              <div className="space-y-2">
                <label className="text-sm font-medium text-slate-700">Confirmar senha</label>
                <input
                  type="password"
                  value={passwordConfirmation}
                  onChange={(event) => setPasswordConfirmation(event.target.value)}
                  placeholder="Repita a senha"
                  className="input-modern"
                  required
                />
              </div>
            </div>

            {error && (
              <div className="rounded-xl border border-red-100 bg-red-50 px-4 py-3 text-sm text-red-600">
                {error}
              </div>
            )}

            <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
              <p className="text-sm text-slate-500">
                Ao concluir, sua conta ja entra no sistema e segue para a configuracao da loja.
              </p>
              <button type="submit" disabled={submitting} className="btn-primary min-w-[220px] py-3">
                {submitting ? 'Criando conta...' : 'Criar minha conta'}
              </button>
            </div>
          </form>
        )}
      </motion.div>
    </div>
  );
}
