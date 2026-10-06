import React, { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { leftArrowUrl } from '@/assets/staticUrls';
import { customFetch } from '@/utils/customFetch';
import { useTranslation } from '@/hooks/useTranslation';

type Step = 'EMAIL' | 'VERIFY';

export default function EmailChanging() {
  const [step, setStep] = useState<Step>('EMAIL');
  const [email, setEmail] = useState('');
  const [code, setCode] = useState('');
  const [error, setError] = useState<string | null>(null);
  const [isSubmitting, setIsSubmitting] = useState(false);
  const navigate = useNavigate();

  const t = useTranslation();

  const isValidEmail = (value: string) => /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value);

  const handleEmailChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    setEmail(e.target.value);
    if (error) setError(null);
  };

  const handleCodeChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    setCode(e.target.value);
    if (error) setError(null);
  };

  const handleSubmitEmail = async () => {
    if (!isValidEmail(email)) {
      setError(t('EmailChanging.form.error'));
      return;
    }

    setIsSubmitting(true);
    setError(null);

    try {
      const token = localStorage.getItem('access_token');
      const response = await customFetch(`${import.meta.env.VITE_API_URL}/email/set`, {
        method: 'POST',
        headers: { 
          'Content-Type': 'application/json',
          Authorization: `Bearer ${token}`,
        },
        body: JSON.stringify({ email }),
      });

      if (!response.ok) {
        throw new Error(
          t('EmailChanging.form.errors.sendFailed') || 'Failed to send verification code',
        );
      }

      setStep('VERIFY');
    } catch (err: any) {
      setError(
        err.message || t('EmailChanging.form.errors.somethingWentWrong') || 'Something went wrong',
      );
    } finally {
      setIsSubmitting(false);
    }
  };

  const handleVerifyCode = async () => {
    if (!code.trim()) {
      setError(t('EmailChanging.verification.codeRequired') || 'Please enter verification code');
      return;
    }

    setIsSubmitting(true);
    setError(null);

    try {
      const token = localStorage.getItem('access_token');
      const response = await customFetch(`${import.meta.env.VITE_API_URL}/email/verify`, {
        method: 'POST',
        headers: { 
          'Content-Type': 'application/json',
          Authorization: `Bearer ${token}`, 
        },
        body: JSON.stringify({ code }),
      });

      if (!response.ok) {
        throw new Error(t('EmailChanging.verification.invalidCode') || 'Invalid verification code');
      }

      alert(
        t('EmailChanging.verification.successAlert') ||
          '🎉 Email successfully verified and updated!',
      );
      navigate(-1);
    } catch (err: any) {
      setError(err.message || t('EmailChanging.verification.failed') || 'Verification failed');
    } finally {
      setIsSubmitting(false);
    }
  };

  const isValid = step === 'EMAIL' ? isValidEmail(email) : code.trim().length > 0;

  return (
    <div>
      <header
        style={{
          display: 'flex',
          alignItems: 'center',
          justifyContent: 'space-between',
        }}
      >
        <img
          src={leftArrowUrl}
          alt={t('EmailChanging.header.backAlt')}
          style={{ cursor: 'pointer' }}
          onClick={() => {
            if (step === 'VERIFY') {
              setStep('EMAIL');
              setError(null);
            } else {
              navigate(-1);
            }
          }}
        />
        <h2>
          {step === 'EMAIL'
            ? t('EmailChanging.header.title')
            : t('EmailChanging.verification.title') || 'Verify Email'}
        </h2>
        <svg
          width="16"
          height="16"
          viewBox="0 0 16 16"
          fill="none"
          xmlns="http://www.w3.org/2000/svg"
          style={{ cursor: 'pointer' }}
          onClick={() => navigate(-1)}
          aria-label={t('EmailChanging.header.closeAlt')}
        >
          <path
            fillRule="evenodd"
            clipRule="evenodd"
            d="M8 10.1221L13.303 15.4251C13.5844 15.7065 13.966 15.8646 14.364 15.8646C14.762 15.8646 15.1436 15.7065 15.425 15.4251C15.7064 15.1437 15.8645 14.7621 15.8645 14.3641C15.8645 13.9662 15.7064 13.5845 15.425 13.3031L10.12 8.00011L15.424 2.69711C15.5633 2.55778 15.6737 2.39238 15.7491 2.21036C15.8244 2.02834 15.8632 1.83326 15.8631 1.63626C15.8631 1.43926 15.8242 1.2442 15.7488 1.06221C15.6734 0.880224 15.5628 0.714877 15.4235 0.57561C15.2842 0.436343 15.1188 0.325884 14.9367 0.250538C14.7547 0.175193 14.5596 0.136437 14.3626 0.136483C14.1656 0.13653 13.9706 0.175377 13.7886 0.250809C13.6066 0.32624 13.4413 0.436778 13.302 0.57611L8 5.87911L2.697 0.57611C2.5587 0.432781 2.39323 0.318431 2.21027 0.239733C2.0273 0.161034 1.83049 0.119563 1.63132 0.117739C1.43215 0.115915 1.23462 0.153774 1.05024 0.229109C0.865859 0.304443 0.698329 0.415744 0.557424 0.556516C0.416519 0.697288 0.305061 0.864713 0.229553 1.04902C0.154045 1.23333 0.115999 1.43083 0.117635 1.63C0.119271 1.82917 0.160556 2.02602 0.239082 2.20906C0.317608 2.3921 0.431802 2.55767 0.575001 2.69611L5.88 8.00011L0.576001 13.3041C0.432803 13.4425 0.318609 13.6081 0.240083 13.7912C0.161557 13.9742 0.120271 14.1711 0.118635 14.3702C0.116999 14.5694 0.155045 14.7669 0.230553 14.9512C0.306061 15.1355 0.417519 15.3029 0.558424 15.4437C0.699329 15.5845 0.866858 15.6958 1.05124 15.7711C1.23562 15.8464 1.43315 15.8843 1.63232 15.8825C1.83149 15.8807 2.0283 15.8392 2.21127 15.7605C2.39423 15.6818 2.5597 15.5674 2.698 15.4241L8 10.1221Z"
            fill="#183D69"
          />
        </svg>
      </header>

      <div
        className="auth-input-block"
        style={{
          borderRadius: '16px',
          boxShadow: '0 2px 4px 2px rgba(0, 0, 0, 0.1)',
          padding: '16px',
          margin: '16px',
        }}
      >
        <h2 style={{ color: '#183D69', fontWeight: '600', fontSize: '20px' }}>
          {step === 'EMAIL'
            ? t('EmailChanging.form.title')
            : t('EmailChanging.verification.inputTitle') || 'Enter verification code'}
        </h2>

        {error && (
          <div className="email-error" style={{ color: '#FF6B6B', marginTop: '8px' }}>
            <p>{error}</p>
          </div>
        )}

        {step === 'EMAIL' ? (
          <div className="auth-email-input" style={{ marginTop: '12px' }}>
            <label style={{ color: '#183D69', fontSize: '14px' }}>
              {t('EmailChanging.form.label')}
            </label>
            <input
              type="email"
              value={email}
              onChange={handleEmailChange}
              placeholder={t('EmailChanging.form.placeholder')}
              className="custom-input"
              style={{
                borderColor: error ? '#FF6B6B' : '#C2D1E1',
                padding: '16px',
                fontSize: '14px',
                borderRadius: '16px',
                borderStyle: 'solid',
                borderWidth: '1px',
                width: '100%',
                marginTop: '4px',
              }}
            />
          </div>
        ) : (
          <div className="auth-code-input" style={{ marginTop: '12px' }}>
            <label style={{ color: '#183D69', fontSize: '14px' }}>
              {t('EmailChanging.verification.label') || `Verification code sent to ${email}`}
            </label>
            <input
              type="text"
              value={code}
              onChange={handleCodeChange}
              placeholder={t('EmailChanging.verification.placeholder') || '123456'}
              maxLength={6}
              className="custom-input"
              style={{
                borderColor: error ? '#FF6B6B' : '#C2D1E1',
                padding: '16px',
                fontSize: '14px',
                borderRadius: '16px',
                borderStyle: 'solid',
                borderWidth: '1px',
                width: '100%',
                marginTop: '4px',
              }}
            />
          </div>
        )}

        <div style={{ marginTop: '16px' }}>
          <button
            onClick={step === 'EMAIL' ? handleSubmitEmail : handleVerifyCode}
            disabled={!isValid || isSubmitting}
            style={{
              backgroundColor: !isValid ? '#879AB1' : '#183D69',
              color: !isValid ? '#C8D1DC' : '#F7B233',
              padding: '15px 0px',
              borderRadius: '16px',
              fontWeight: 600,
              fontSize: '14px',
              width: '100%',
              border: 'none',
              cursor: isValid && !isSubmitting ? 'pointer' : 'default',
              position: 'relative',
              minWidth: '120px',
            }}
          >
            {isSubmitting ? (
              <span
                style={{
                  width: '18px',
                  height: '18px',
                  border: '2px solid #F7B233',
                  borderTopColor: 'transparent',
                  borderRadius: '50%',
                  animation: 'spin 0.7s linear infinite',
                  display: 'inline-block',
                }}
              />
            ) : step === 'EMAIL' ? (
              t('EmailChanging.form.button.set')
            ) : (
              t('EmailChanging.verification.button.verify') || 'Verify'
            )}
          </button>
        </div>
      </div>

      <style>
        {`
          @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
          }
        `}
      </style>
    </div>
  );
}
